<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\Wastage;
use App\Models\User;
use App\Services\ConsumptionService;
use App\Services\FinancialAccountService;
use App\Services\ProfitLossService;
use App\Services\PurchaseService;
use App\Services\ReversalService;
use App\Services\StockLedgerService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierCreditNotesAndRefundsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected FinancialAccount $cashAccount;
    protected FinancialAccount $bankAccount;
    protected FinancialAccount $bkashAccount;
    protected ReversalService $reversalService;
    protected SupplierService $supplierService;
    protected PurchaseService $purchaseService;
    protected ConsumptionService $consumptionService;
    protected ProfitLossService $profitLossService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@royalnest.com',
            'is_super_admin' => true,
        ]);

        $this->cashAccount = FinancialAccount::create([
            'name' => 'Cash Drawer',
            'type' => 'cash',
            'account_number' => 'CASH-001',
            'current_balance' => 50000.00,
            'is_active' => true,
        ]);

        $this->bankAccount = FinancialAccount::create([
            'name' => 'BRAC Bank Current',
            'type' => 'bank',
            'account_number' => '15012034001',
            'current_balance' => 100000.00,
            'is_active' => true,
        ]);

        $this->bkashAccount = FinancialAccount::create([
            'name' => 'bKash Merchant',
            'type' => 'bkash',
            'account_number' => '01711000000',
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);

        $accountService = app(FinancialAccountService::class);
        $stockLedgerService = app(StockLedgerService::class);

        $this->reversalService = new ReversalService($accountService, $stockLedgerService);
        $this->supplierService = app(SupplierService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->consumptionService = app(ConsumptionService::class);
        $this->profitLossService = app(ProfitLossService::class);
    }

    protected function createProductAndSupplier(string $name = 'Rahim Meat Supply', float $initialSupplierBalance = 0.00): array
    {
        $category = Category::create([
            'name' => 'Meat & Poultry',
            'slug' => 'meat-poultry',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => $name,
            'company_name' => $name,
            'contact_person' => 'Rahim',
            'phone' => '01711223344',
            'category' => 'Meat',
            'status' => 'active',
            'opening_balance' => $initialSupplierBalance,
            'current_balance' => $initialSupplierBalance,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Boneless Beef',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 0.0,
            'cost_price' => 750.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'multiplier_to_base' => 1.0,
        ]);

        return [$supplier, $product];
    }

    /**
     * 1. Voiding a mistaken unpaid purchase does not create a fake account deposit.
     */
    public function test_voiding_mistaken_unpaid_purchase_does_not_create_fake_account_deposit(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier();

        $initialCashBalance = (float) $this->cashAccount->current_balance;

        // Unpaid purchase of 10 kg @ ৳750 = ৳7,500
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-UNPAID-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $this->assertEquals(7500.00, (float) $supplier->fresh()->current_balance);
        $this->assertEquals(10.0, (float) $product->fresh()->current_stock);
        $this->assertEquals($initialCashBalance, (float) $this->cashAccount->fresh()->current_balance);

        // Void the mistaken purchase
        $result = $this->reversalService->voidPurchase($purchase, 'Mistaken double entry');

        $this->assertTrue($result['success']);
        // Stock reversed
        $this->assertEquals(0.0, (float) $product->fresh()->current_stock);
        // Supplier due restored to 0
        $this->assertEquals(0.00, (float) $supplier->fresh()->current_balance);
        // Zero fake deposit: Cash Drawer balance remains completely untouched
        $this->assertEquals($initialCashBalance, (float) $this->cashAccount->fresh()->current_balance);
    }

    /**
     * 2. A genuine supplier refund increases only the selected account by the actual received amount.
     */
    public function test_genuine_supplier_refund_increases_only_selected_account_by_actual_amount(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Kader Poultry', -5000.00); // ৳5,000 credit balance

        $initialCash = (float) $this->cashAccount->current_balance;
        $initialBank = (float) $this->bankAccount->current_balance;
        $initialBkash = (float) $this->bkashAccount->current_balance;

        // Supplier refunds ৳3,000 via bKash
        $this->reversalService->recordSupplierRefund(
            supplier: $supplier,
            amount: 3000.00,
            receivingAccountId: $this->bkashAccount->id,
            refundDate: '2026-11-13',
            reference: 'REF-BKASH-001',
            notes: 'Partial cash refund for returned poultry'
        );

        // Only bKash account increased by exactly ৳3,000
        $this->assertEquals($initialBkash + 3000.00, (float) $this->bkashAccount->fresh()->current_balance);
        $this->assertEquals($initialCash, (float) $this->cashAccount->fresh()->current_balance);
        $this->assertEquals($initialBank, (float) $this->bankAccount->fresh()->current_balance);

        // Supplier credit balance reduced from -5000 to -2000
        $this->assertEquals(-2000.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 3. A purchase return without a cash refund does not increase any financial account.
     */
    public function test_purchase_return_without_cash_refund_does_not_increase_any_financial_account(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier();

        // Purchase paid from Cash Drawer
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-PAID-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 6000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 8.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $cashAfterPurchase = (float) $this->cashAccount->fresh()->current_balance;
        $this->assertEquals(0.00, (float) $supplier->fresh()->current_balance); // fully paid

        // Goods returned to supplier (no cash refund handed back)
        $this->reversalService->voidPurchase($purchase, 'Return bad batch of beef');

        // Financial account NEVER increases without physical cash refund
        $this->assertEquals($cashAfterPurchase, (float) $this->cashAccount->fresh()->current_balance);
        // Supplier balance turns into a credit note of ৳6,000 (-6000.00)
        $this->assertEquals(-6000.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 4. A fully paid purchase return creates the correct supplier credit balance.
     */
    public function test_fully_paid_purchase_return_creates_correct_supplier_credit_balance(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier();

        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-FULL-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 7500.00,
                'payment_account_id' => $this->bankAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $this->assertEquals(0.00, (float) $supplier->fresh()->current_balance);

        $this->reversalService->voidPurchase($purchase, 'Vendor agreed to take back full order');

        // Full ৳7,500 becomes supplier credit balance
        $this->assertEquals(-7500.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 5. A partially unpaid purchase return reduces payable correctly and preserves any excess supplier credit.
     */
    public function test_partially_unpaid_purchase_return_reduces_payable_and_preserves_excess_credit(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier();

        // 10 kg @ ৳750 = ৳7,500. Paid ৳2,000, Unpaid Due ৳5,500.
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-PARTIAL-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 2000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $this->assertEquals(5500.00, (float) $supplier->fresh()->current_balance);

        // Kitchen consumes 1 kg (৳750). Remaining 9 kg (worth ৳6,750) is returned.
        $this->consumptionService->recordConsumption(
            date: '2026-11-13',
            items: [['product_id' => $product->id, 'quantity_used' => 1.0]],
            userId: $this->admin->id,
            notes: 'Test batch sample'
        );

        // Unpaid due was ৳5,500. Return value is ৳6,750.
        // Capping at unpaid due would have thrown away ৳1,250 of credit!
        // Full returned value reduction: ৳5,500 - ৳6,750 = -৳1,250.
        $result = $this->reversalService->voidPurchase($purchase, 'Return 9 kg intact beef', true);

        $this->assertTrue($result['success']);
        $this->assertEquals(6750.00, $result['returned_value']);
        // Due payable wiped to zero AND excess credit of ৳1,250 preserved!
        $this->assertEquals(-1250.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 6. Supplier credit applied to a future purchase is not counted as new income.
     */
    public function test_supplier_credit_applied_to_future_purchase_is_not_counted_as_new_income(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Salim Dairy', -4000.00); // ৳4,000 credit note

        // Buy ৳10,000 on credit (paid_amount = 0)
        $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-NEW-BILL-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 1000.00,
                ],
            ],
            userId: $this->admin->id
        );

        // Balance was -৳4,000. New bill is +৳10,000.
        // Resulting balance: -4,000 + 10,000 = +6,000 (credit was automatically absorbed!)
        $this->assertEquals(6000.00, (float) $supplier->fresh()->current_balance);

        // Verify P&L: credit application does not generate fake revenue
        $pnl = $this->profitLossService->getDailySummary('2026-11-13');
        $this->assertEquals(0.00, $pnl['sales']['total_sales']);
        $this->assertEquals(0.00, $pnl['gross_profit']);
    }

    /**
     * 7. A refund and credit note cannot both credit the restaurant twice.
     */
    public function test_refund_and_credit_note_cannot_both_credit_restaurant_twice(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Habib Fish', -5000.00); // ৳5,000 credit note

        $initialCash = (float) $this->cashAccount->current_balance;

        // Settle the credit note via real cash refund
        $this->reversalService->recordSupplierRefund(
            supplier: $supplier,
            amount: 5000.00,
            receivingAccountId: $this->cashAccount->id,
            refundDate: '2026-11-13',
            reference: 'REF-HABIB-001',
            notes: 'Cash refund for credit balance'
        );

        $this->assertEquals($initialCash + 5000.00, (float) $this->cashAccount->fresh()->current_balance);
        $this->assertEquals(0.00, (float) $supplier->fresh()->current_balance);

        // Attempting to record ANOTHER refund now that credit balance is 0 is strictly blocked
        $this->expectException(ValidationException::class);
        $this->reversalService->recordSupplierRefund(
            supplier: $supplier,
            amount: 5000.00,
            receivingAccountId: $this->cashAccount->id,
            refundDate: '2026-11-13',
            reference: 'REF-HABIB-DUP',
            notes: 'Duplicate refund attempt'
        );
    }

    /**
     * 8. FIFO stock and consumption history remain intact during partial return.
     */
    public function test_fifo_stock_and_consumption_history_remain_intact_during_partial_return(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier();

        // Purchase 10 kg
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-FIFO-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 7500.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $batch = StockBatch::where('purchase_item_id', $purchase->items->first()->id)->first();

        // Cook 4 kg
        $consumption = $this->consumptionService->recordConsumption(
            date: '2026-11-13',
            items: [['product_id' => $product->id, 'quantity_used' => 4.0]],
            userId: $this->admin->id,
            notes: 'Steak cooking'
        );

        $this->assertEquals(6.0, (float) $batch->fresh()->remaining_quantity);
        $this->assertEquals(6.0, (float) $product->fresh()->current_stock);

        // Return remaining 6 kg
        $this->reversalService->voidPurchase($purchase, 'Return remaining 6kg steaks', true);

        // Stock batch exhausted
        $this->assertEquals(0.0, (float) $batch->fresh()->remaining_quantity);
        $this->assertTrue((bool) $batch->fresh()->is_exhausted);
        $this->assertEquals(0.0, (float) $product->fresh()->current_stock);

        // Kitchen consumption record remains 100% intact (4 kg @ ৳750 = ৳3,000 food cost)
        $this->assertDatabaseHas('consumption_items', [
            'consumption_id' => $consumption->id,
            'product_id' => $product->id,
            'quantity_used' => 4.0,
            'unit_cost' => 750.00,
            'total_cost' => 3000.00,
        ]);
    }

    /**
     * 9. Duplicate purchase reversal and excess refund are strictly prevented.
     */
    public function test_duplicate_purchase_reversal_and_excess_refund_are_prevented(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Faruk Veg', -2000.00); // ৳2,000 credit note

        // 1. Attempting refund exceeding credit balance throws ValidationException
        try {
            $this->reversalService->recordSupplierRefund(
                supplier: $supplier,
                amount: 3000.00, // exceeds 2000
                receivingAccountId: $this->cashAccount->id,
                refundDate: '2026-11-13',
                reference: 'REF-EXCESS',
                notes: 'Too much'
            );
            $this->fail('Expected ValidationException for excess refund');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('exceeds', $e->getMessage());
        }

        // 2. Voiding already-voided purchase throws ValidationException
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-DUP-TEST',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 2.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $this->reversalService->voidPurchase($purchase, 'First void');

        try {
            $this->reversalService->voidPurchase($purchase, 'Second void attempt');
            $this->fail('Expected ValidationException for duplicate void');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been reversed or voided', $e->getMessage());
        }
    }

    /**
     * 10. A purchase with recorded payment that was never physically made does not create a fake account deposit on void.
     */
    public function test_recorded_payment_without_actual_money_movement_drawer_behavior(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Data Entry Mistake Supplier');

        // User enters a purchase with recorded payment of ৳3,000 from Cash Drawer by mistake
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-TYPO-001',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 3000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 4.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00,
                ],
            ],
            userId: $this->admin->id
        );

        $cashAfterEntry = (float) $this->cashAccount->fresh()->current_balance;
        $this->assertEquals(47000.00, $cashAfterEntry);

        // Void the purchase: System does NOT create an artificial deposit into the drawer
        $this->reversalService->voidPurchase($purchase, 'Cancel mistake entry');

        // Cash drawer balance is not falsely inflated
        $this->assertEquals(47000.00, (float) $this->cashAccount->fresh()->current_balance);
        // Supplier balance reflects the ৳3,000 credit note
        $this->assertEquals(-3000.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 11. Credit note applied to multiple future purchases and partial credit application.
     */
    public function test_credit_note_applied_to_multiple_future_purchases_and_partial_credit_application(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Multi Bill Supplier', -10000.00); // ৳10,000 credit note

        // Future Purchase A: ৳6,000 unpaid
        $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-FUTURE-A',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 8.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 750.00, // ৳6,000
                ],
            ],
            userId: $this->admin->id
        );

        // Supplier balance: -10,000 + 6,000 = -4,000 (Remaining credit: ৳4,000)
        $this->assertEquals(-4000.00, (float) $supplier->fresh()->current_balance);

        // Future Purchase B: ৳7,000 unpaid
        $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-FUTURE-B',
                'purchase_date' => '2026-11-14',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 7.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 1000.00, // ৳7,000
                ],
            ],
            userId: $this->admin->id
        );

        // Supplier balance: -4,000 + 7,000 = +3,000 (Net due payable: ৳3,000)
        $this->assertEquals(3000.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 12. Statement running balance and invoice-level outstanding dues reconcile completely.
     */
    public function test_statement_running_balance_and_invoice_level_dues_reconcile(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Reconcile Vendor', 0.00);

        // Purchase 1: ৳10,000 paid in full from Cash Drawer
        $purchase1 = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-REC-1',
                'purchase_date' => '2026-11-10',
                'paid_amount' => 10000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 1000.00,
                ],
            ],
            userId: $this->admin->id
        );

        // Purchase 1 returned -> Generates ৳10,000 Credit Note
        $this->reversalService->voidPurchase($purchase1, 'Return intact batch for credit note');
        $this->assertEquals(-10000.00, (float) $supplier->fresh()->current_balance);

        // Purchase 2: ৳6,000 confirmed unpaid
        $purchase2 = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-REC-2',
                'purchase_date' => '2026-11-11',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 6.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 1000.00,
                ],
            ],
            userId: $this->admin->id
        );

        // Purchase 3: ৳8,000 confirmed unpaid
        $purchase3 = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-REC-3',
                'purchase_date' => '2026-11-12',
                'paid_amount' => 0.00,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 8.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 1000.00,
                ],
            ],
            userId: $this->admin->id
        );

        // Net supplier balance: -10,000 + 6,000 + 8,000 = +4,000.00
        $this->assertEquals(4000.00, (float) $supplier->fresh()->current_balance);

        // Generate supplier statement
        $statement = $this->supplierService->getSupplierStatement($supplier->id);

        $this->assertEquals(4000.00, (float) $statement['current_balance']);

        // Check statement rows: Last row running due must equal current balance (৳4,000)
        $lastRow = end($statement['statement_rows']);
        $this->assertEquals(4000.00, (float) $lastRow['running_due']);

        // Check invoice-level allocations:
        // Purchase 2 (৳6,000) should be fully settled by ৳6,000 of the credit note -> remaining due = 0
        $p2Row = collect($statement['statement_rows'])->firstWhere('reference', 'INV-REC-2');
        $this->assertNotNull($p2Row);
        $this->assertEquals(0.00, (float) $p2Row['remaining_bill_due']);
        $this->assertEquals('paid', $p2Row['bill_status']);

        // Purchase 3 (৳8,000) should have remaining ৳4,000 credit allocated -> remaining due = ৳4,000
        $p3Row = collect($statement['statement_rows'])->firstWhere('reference', 'INV-REC-3');
        $this->assertNotNull($p3Row);
        $this->assertEquals(4000.00, (float) $p3Row['remaining_bill_due']);
        $this->assertEquals('partially_paid', $p3Row['bill_status']);

        // Invariant: Sum of active bill remaining dues equals current balance!
        $totalActiveBillDues = (float) $p2Row['remaining_bill_due'] + (float) $p3Row['remaining_bill_due'];
        $this->assertEquals(4000.00, $totalActiveBillDues);
    }

    /**
     * 13. Duplicate refund reference is strictly prevented.
     */
    public function test_duplicate_refund_reference_is_strictly_prevented(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('Idempotent Refund Vendor', -5000.00);

        // First refund of ৳2,000 with reference REF-UNIQUE-001 succeeds
        $this->reversalService->recordSupplierRefund(
            supplier: $supplier,
            amount: 2000.00,
            receivingAccountId: $this->cashAccount->id,
            refundDate: '2026-11-13',
            reference: 'REF-UNIQUE-001',
            notes: 'First refund'
        );

        $this->assertEquals(-3000.00, (float) $supplier->fresh()->current_balance);

        // Attempting to record another refund with the EXACT same reference is blocked
        try {
            $this->reversalService->recordSupplierRefund(
                supplier: $supplier,
                amount: 1000.00,
                receivingAccountId: $this->cashAccount->id,
                refundDate: '2026-11-13',
                reference: 'REF-UNIQUE-001',
                notes: 'Resubmitted duplicate reference'
            );
            $this->fail('Expected ValidationException for duplicate refund reference');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been recorded', $e->getMessage());
        }
    }

    /**
     * 14. FIFO partial return after consumption and wastage preserves history.
     */
    public function test_fifo_partial_return_after_consumption_and_wastage(): void
    {
        $this->actingAs($this->admin);
        [$supplier, $product] = $this->createProductAndSupplier('FIFO Wastage Vendor');

        // Purchase 10 kg @ ৳500 = ৳5,000
        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-FIFO-WASTE',
                'purchase_date' => '2026-11-13',
                'paid_amount' => 5000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10.0,
                    'purchase_unit' => 'kg',
                    'unit_price' => 500.00,
                ],
            ],
            userId: $this->admin->id
        );

        $batch = StockBatch::where('purchase_item_id', $purchase->items->first()->id)->first();

        // 1. Kitchen consumes 3 kg (৳1,500)
        $consumption = $this->consumptionService->recordConsumption(
            date: '2026-11-13',
            items: [['product_id' => $product->id, 'quantity_used' => 3.0]],
            userId: $this->admin->id,
            notes: 'Kitchen lunch curry'
        );

        $this->assertEquals(7.0, (float) $batch->fresh()->remaining_quantity);
        $this->assertEquals(7.0, (float) $product->fresh()->current_stock);

        // 2. Kitchen records 2 kg wastage (burnt/spoiled, ৳1,000)
        Wastage::create([
            'product_id' => $product->id,
            'quantity' => 2.0,
            'unit_cost' => 500.00,
            'total_cost' => 1000.00,
            'wastage_date' => '2026-11-13',
            'reason' => 'burnt',
            'notes' => 'Burnt on high flame',
            'created_by' => $this->admin->id,
        ]);
        $batch->decrement('remaining_quantity', 2.0);
        $product->decrement('current_stock', 2.0);

        $this->assertEquals(5.0, (float) $batch->fresh()->remaining_quantity);
        $this->assertEquals(5.0, (float) $product->fresh()->current_stock);

        // 3. Return remaining 5 kg intact shelf stock to vendor with credit note
        $result = $this->reversalService->voidPurchase($purchase, 'Return remaining 5kg', true);

        $this->assertTrue($result['success']);
        $this->assertEquals(2500.00, $result['returned_value']); // 5 kg @ ৳500 = ৳2,500

        // Stock is now 0 kg and batch is exhausted
        $this->assertEquals(0.0, (float) $product->fresh()->current_stock);
        $this->assertEquals(0.0, (float) $batch->fresh()->remaining_quantity);
        $this->assertTrue((bool) $batch->fresh()->is_exhausted);

        // Supplier balance: ৳2,500 credit note
        $this->assertEquals(-2500.00, (float) $supplier->fresh()->current_balance);

        // Historical consumption (3 kg, ৳1,500) and wastage (2 kg, ৳1,000) remain 100% intact
        $this->assertDatabaseHas('consumption_items', [
            'consumption_id' => $consumption->id,
            'product_id' => $product->id,
            'quantity_used' => 3.0,
            'total_cost' => 1500.00,
        ]);

        $this->assertDatabaseHas('wastages', [
            'product_id' => $product->id,
            'quantity' => 2.0,
            'total_cost' => 1000.00,
        ]);
    }
}
