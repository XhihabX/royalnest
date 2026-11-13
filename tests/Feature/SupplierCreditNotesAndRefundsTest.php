<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\Supplier;
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
}
