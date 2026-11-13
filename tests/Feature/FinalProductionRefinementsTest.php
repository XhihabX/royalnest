<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Consumption;
use App\Models\ConsumptionItem;
use App\Models\DailySale;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SalaryPayment;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ConsumptionService;
use App\Services\FinancialAccountService;
use App\Services\ProfitLossService;
use App\Services\ReversalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalProductionRefinementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Branch $branch;
    protected FinancialAccount $cashAccount;
    protected ProfitLossService $profitLossService;
    protected ReversalService $reversalService;
    protected ConsumptionService $consumptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Royal Nest Main Branch',
            'code' => 'RN-01',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'branch_id' => $this->branch->id,
            'name' => 'Royal Nest Owner',
            'email' => 'admin@royalnest.com',
            'phone' => '01711112222',
            'primary_role' => 'owner',
            'is_super_admin' => true,
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        $this->cashAccount = FinancialAccount::create([
            'branch_id' => $this->branch->id,
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'opening_balance' => 20000.00,
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);

        $this->profitLossService = app(ProfitLossService::class);
        $this->reversalService = app(ReversalService::class);
        $this->consumptionService = app(ConsumptionService::class);
    }

    /**
     * 1. Active sales are included in P&L.
     */
    public function test_active_sales_are_included_in_pnl(): void
    {
        DailySale::create([
            'branch_id' => $this->branch->id,
            'sales_date' => '2026-11-13',
            'total_sales' => 15000.00,
            'cash_sales' => 15000.00,
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $summary = $this->profitLossService->getDailySummary('2026-11-13');
        $this->assertEquals(15000.00, $summary['sales']['total_sales']);
        $this->assertEquals(15000.00, $summary['gross_profit']);
    }

    /**
     * 2. Voided sales are excluded from P&L.
     */
    public function test_voided_sales_are_excluded_from_pnl(): void
    {
        DailySale::create([
            'branch_id' => $this->branch->id,
            'sales_date' => '2026-11-13',
            'total_sales' => 20000.00,
            'cash_sales' => 20000.00,
            'reversal_status' => 'voided',
            'created_by' => $this->admin->id,
        ]);

        $summary = $this->profitLossService->getDailySummary('2026-11-13');
        $this->assertEquals(0.00, $summary['sales']['total_sales']);
        $this->assertEquals(0.00, $summary['gross_profit']);

        $range = $this->profitLossService->getRangeSummary('2026-11-01', '2026-11-30');
        $this->assertEquals(0.00, $range['total_sales']);
    }

    /**
     * 3. Legacy sales with NULL reversal status remain included.
     */
    public function test_legacy_sales_with_null_reversal_status_remain_included(): void
    {
        // Insert record directly with NULL reversal_status mimicking pre-enhancement legacy data
        $saleId = DB::table('daily_sales')->insertGetId([
            'sales_date' => '2026-11-10',
            'total_sales' => 12500.00,
            'cash_sales' => 12500.00,
            'bkash_sales' => 0.00,
            'nagad_sales' => 0.00,
            'card_bank_sales' => 0.00,
            'other_sales' => 0.00,
            'discrepancy_amount' => 0.00,
            'reversal_status' => null,
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(DB::table('daily_sales')->where('id', $saleId)->value('reversal_status'));

        $summary = $this->profitLossService->getDailySummary('2026-11-10');
        $this->assertEquals(12500.00, $summary['sales']['total_sales']);

        $range = $this->profitLossService->getRangeSummary('2026-11-01', '2026-11-30');
        $this->assertEquals(12500.00, $range['total_sales']);
    }

    /**
     * 4. Active expenses are included in P&L.
     */
    public function test_active_expenses_are_included_in_pnl(): void
    {
        OperatingExpense::create([
            'branch_id' => $this->branch->id,
            'expense_date' => '2026-11-13',
            'category' => 'electricity',
            'name' => 'November Electricity',
            'amount' => 3500.00,
            'payment_account_id' => $this->cashAccount->id,
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $summary = $this->profitLossService->getDailySummary('2026-11-13');
        $this->assertEquals(3500.00, $summary['operating_costs']['operating_expenses']);

        $range = $this->profitLossService->getRangeSummary('2026-11-01', '2026-11-30');
        $this->assertEquals(3500.00, $range['operating_expenses']);
    }

    /**
     * 5. Voided expenses are excluded from P&L.
     */
    public function test_voided_expenses_are_excluded_from_pnl(): void
    {
        OperatingExpense::create([
            'branch_id' => $this->branch->id,
            'expense_date' => '2026-11-13',
            'category' => 'rent',
            'name' => 'Mistaken Duplicate Rent',
            'amount' => 25000.00,
            'payment_account_id' => $this->cashAccount->id,
            'reversal_status' => 'voided',
            'created_by' => $this->admin->id,
        ]);

        $summary = $this->profitLossService->getDailySummary('2026-11-13');
        $this->assertEquals(0.00, $summary['operating_costs']['operating_expenses']);

        $range = $this->profitLossService->getRangeSummary('2026-11-01', '2026-11-30');
        $this->assertEquals(0.00, $range['operating_expenses']);
    }

    /**
     * 6. Voided purchases do not inflate active transaction totals.
     */
    public function test_voided_purchases_do_not_inflate_active_transaction_totals(): void
    {
        $this->actingAs($this->admin);

        $category = Category::create([
            'name' => 'Raw Materials',
            'slug' => 'raw-materials',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Bengal Agro',
            'company_name' => 'Bengal Agro',
            'contact_person' => 'Rafiq',
            'phone' => '01811223344',
            'category' => 'Produce',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Polao Rice',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 50,
            'cost_price' => 140.00,
            'is_active' => true,
        ]);

        // Purchase 1: Active (৳1400)
        $purchase1 = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-ACTIVE-01',
            'purchase_date' => '2026-11-13',
            'total_amount' => 1400.00,
            'paid_amount' => 1400.00,
            'status' => 'paid',
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase1->id,
            'product_id' => $product->id,
            'purchase_quantity' => 10,
            'purchase_unit' => 'kg',
            'base_quantity' => 10,
            'base_unit_cost' => 140.00,
            'unit_price' => 140.00,
            'subtotal' => 1400.00,
        ]);

        // Purchase 2: Voided (৳2800)
        $purchase2 = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-VOIDED-02',
            'purchase_date' => '2026-11-13',
            'total_amount' => 2800.00,
            'paid_amount' => 0.00,
            'status' => 'due',
            'reversal_status' => 'voided',
            'created_by' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase2->id,
            'product_id' => $product->id,
            'purchase_quantity' => 20,
            'purchase_unit' => 'kg',
            'base_quantity' => 20,
            'base_unit_cost' => 140.00,
            'unit_price' => 140.00,
            'subtotal' => 2800.00,
        ]);

        $response = $this->get('/reports/transactions?date=2026-11-13&mode=daily');
        $response->assertOk();

        // Active totals should only count the ৳1400 active purchase, NOT the ৳2800 voided one
        $summary = $response->viewData('page')['props']['summary'];
        $this->assertEquals(1400.00, $summary['total_purchases']);
        $this->assertEquals(1400.00, $summary['total_raw_materials']);
    }

    /**
     * 7. Voided supplier and salary payments do not inflate active outflow totals.
     */
    public function test_voided_supplier_and_salary_payments_do_not_inflate_active_outflow_totals(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::create([
            'name' => 'Desh Meat Supplier',
            'company_name' => 'Desh Meat Supplier',
            'contact_person' => 'Kamal',
            'phone' => '01911223344',
            'category' => 'Meat',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        $employee = Employee::create([
            'branch_id' => $this->branch->id,
            'name' => 'Head Chef Tariq',
            'phone' => '01722334455',
            'role' => 'Head Chef',
            'salary_type' => 'monthly',
            'agreed_salary' => 30000.00,
            'joining_date' => '2026-01-01',
            'status' => 'active',
        ]);

        // Active Supplier Payment: ৳5,000
        SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'voucher_no' => 'PAY-ACTIVE-01',
            'payment_date' => '2026-11-13',
            'amount' => 5000.00,
            'payment_account_id' => $this->cashAccount->id,
            'payment_method' => 'cash',
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Voided Supplier Payment: ৳15,000
        SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'voucher_no' => 'PAY-VOIDED-02',
            'payment_date' => '2026-11-13',
            'amount' => 15000.00,
            'payment_account_id' => $this->cashAccount->id,
            'payment_method' => 'cash',
            'reversal_status' => 'voided',
            'created_by' => $this->admin->id,
        ]);

        // Active Salary Payment: ৳8,000
        SalaryPayment::create([
            'employee_id' => $employee->id,
            'payment_date' => '2026-11-13',
            'month_year' => '2026-11',
            'amount' => 8000.00,
            'payment_account_id' => $this->cashAccount->id,
            'payment_type' => 'salary_payment',
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Voided Salary Payment: ৳20,000
        SalaryPayment::create([
            'employee_id' => $employee->id,
            'payment_date' => '2026-11-13',
            'month_year' => '2026-11',
            'amount' => 20000.00,
            'payment_account_id' => $this->cashAccount->id,
            'payment_type' => 'salary_payment',
            'reversal_status' => 'voided',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get('/reports/transactions?date=2026-11-13&mode=daily');
        $response->assertOk();

        $summary = $response->viewData('page')['props']['summary'];
        $this->assertEquals(5000.00, $summary['total_supplier_payments']);
        $this->assertEquals(8000.00, $summary['total_salary_payments']);
        $this->assertEquals(13000.00, $summary['total_liability_settlements']);
        $this->assertEquals(13000.00, $summary['total_account_outflows']);
        $this->assertEquals(13000.00, $summary['actual_cash_outflows']);
        $this->assertEquals(0.00, $summary['digital_bank_outflows']);
        $this->assertEquals(13000.00, $summary['total_cash_outflows']);
    }

    /**
     * 8. Historical voided transactions remain visible with their correct status.
     */
    public function test_historical_voided_transactions_remain_visible_with_correct_status(): void
    {
        $this->actingAs($this->admin);

        OperatingExpense::create([
            'branch_id' => $this->branch->id,
            'expense_date' => '2026-11-13',
            'category' => 'repairs',
            'payee' => 'Cancelled Oven Repair',
            'amount' => 1200.00,
            'payment_account_id' => $this->cashAccount->id,
            'reversal_status' => 'voided',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get('/reports/transactions?date=2026-11-13&mode=daily');
        $response->assertOk();

        $outflows = $response->viewData('page')['props']['allOutflows'];
        $this->assertCount(1, $outflows);
        $this->assertEquals('voided', $outflows[0]['reversal_status']);
        $this->assertEquals('Cancelled Oven Repair', $outflows[0]['name']);
    }

    /**
     * 9. Actual refunds are not incorrectly treated as ordinary voids.
     */
    public function test_actual_refunds_are_not_incorrectly_treated_as_ordinary_voids(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::create([
            'name' => 'Fish Market Vendor',
            'company_name' => 'Fish Market Vendor',
            'contact_person' => 'Shujon',
            'phone' => '01777889900',
            'category' => 'Fish',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => -2500.00, // ৳2500 credit balance owed by vendor to restaurant
        ]);

        $initialBalance = (float) $this->cashAccount->current_balance;

        // Record physical cash refund received from supplier
        $this->reversalService->recordSupplierRefund(
            supplier: $supplier,
            amount: 2500.00,
            receivingAccountId: $this->cashAccount->id,
            refundDate: '2026-11-13',
            reference: 'REF-FISH-001',
            notes: 'Returned spoiled prawns refund'
        );

        // Account receives real deposit
        $this->assertEquals($initialBalance + 2500.00, (float) $this->cashAccount->fresh()->current_balance);
        // Supplier credit balance cleared to 0
        $this->assertEquals(0.00, (float) $supplier->fresh()->current_balance);
    }

    /**
     * 10. Bangladesh timezone is respected around midnight.
     */
    public function test_bangladesh_timezone_is_respected_around_midnight(): void
    {
        $this->assertEquals('Asia/Dhaka', config('app.timezone'));

        // Test near-midnight in Bangladesh: 2026-11-13 23:45:00 BST (+06:00)
        // In UTC, this is 2026-11-13 17:45:00 UTC. Both are 2026-11-13.
        $lateNight = Carbon::parse('2026-11-13 23:45:00', 'Asia/Dhaka');
        $this->assertEquals('2026-11-13', $lateNight->toDateString());

        // Test early morning in Bangladesh: 2026-11-14 00:15:00 BST (+06:00)
        // In UTC, this is 2026-11-13 18:15:00 UTC.
        // Under Asia/Dhaka it is cleanly recognized as 2026-11-14.
        $earlyMorning = Carbon::parse('2026-11-14 00:15:00', 'Asia/Dhaka');
        $this->assertEquals('2026-11-14', $earlyMorning->toDateString());
    }

    /**
     * 11. Existing FIFO and inventory consumption calculations remain unchanged.
     */
    public function test_existing_fifo_and_inventory_consumption_calculations_remain_unchanged(): void
    {
        $category = Category::create([
            'name' => 'Baking Goods',
            'slug' => 'baking-goods',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Flour Lot',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 30.0,
            'cost_price' => 50.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'multiplier_to_base' => 1.0,
        ]);

        // Batch 1: 10 kg @ ৳40 (Oldest)
        $batch1 = StockBatch::create([
            'product_id' => $product->id,
            'batch_no' => 'BATCH-FLOUR-01',
            'received_date' => '2026-11-01',
            'initial_quantity' => 10.0,
            'remaining_quantity' => 10.0,
            'unit_cost' => 40.00,
            'is_exhausted' => false,
        ]);

        // Batch 2: 20 kg @ ৳60 (Newer)
        $batch2 = StockBatch::create([
            'product_id' => $product->id,
            'batch_no' => 'BATCH-FLOUR-02',
            'received_date' => '2026-11-05',
            'initial_quantity' => 20.0,
            'remaining_quantity' => 20.0,
            'unit_cost' => 60.00,
            'is_exhausted' => false,
        ]);

        // Consume 15 kg: should take 10 kg @ ৳40 (৳400) + 5 kg @ ৳60 (৳300) = ৳700 COGS
        $consumption = $this->consumptionService->recordConsumption(
            date: '2026-11-13',
            items: [
                ['product_id' => $product->id, 'quantity_used' => 15.0]
            ],
            userId: $this->admin->id,
            notes: 'Bread baking'
        );

        $this->assertEquals(700.00, (float) $consumption->total_cost);
        $this->assertEquals(0.0, (float) $batch1->fresh()->remaining_quantity);
        $this->assertTrue((bool) $batch1->fresh()->is_exhausted);
        $this->assertEquals(15.0, (float) $batch2->fresh()->remaining_quantity);
        $this->assertEquals(15.0, (float) $product->fresh()->current_stock);
    }

    /**
     * 12. Unlinked account outflows are not silently counted as cash or digital.
     */
    public function test_unlinked_account_outflows_are_not_silently_counted_as_cash_or_digital(): void
    {
        $this->actingAs($this->admin);

        OperatingExpense::create([
            'branch_id' => $this->branch->id,
            'expense_date' => '2026-11-13',
            'category' => 'other',
            'payee' => 'Direct Petty Voucher',
            'amount' => 1500.00,
            'payment_account_id' => null, // Unlinked / external account
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get('/reports/transactions?date=2026-11-13&mode=daily');
        $response->assertOk();

        $summary = $response->viewData('page')['props']['summary'];
        $this->assertEquals(1500.00, $summary['total_account_outflows']);
        $this->assertEquals(0.00, $summary['actual_cash_outflows']);
        $this->assertEquals(0.00, $summary['digital_bank_outflows']);
        $this->assertEquals(1500.00, $summary['unresolved_account_outflows']);
    }

    /**
     * 13. Purchase partial reversal edge case returns remaining shelf stock and recalculates payables.
     */
    public function test_purchase_partial_reversal_edge_case_with_credit_note(): void
    {
        $category = Category::create([
            'name' => 'Fresh Meat',
            'slug' => 'fresh-meat',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Amin Meat House',
            'company_name' => 'Amin Meat House',
            'contact_person' => 'Amin',
            'phone' => '01855667788',
            'category' => 'Meat',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => 4000.00, // ৳4000 unpaid due
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Beef Bone-in',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 10.0,
            'cost_price' => 800.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'multiplier_to_base' => 1.0,
        ]);

        // Purchase 10 kg Beef @ ৳800 = ৳8,000 (Paid ৳4,000, Due ৳4,000)
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-BEEF-001',
            'purchase_date' => '2026-11-13',
            'total_amount' => 8000.00,
            'paid_amount' => 4000.00,
            'payment_account_id' => $this->cashAccount->id,
            'status' => 'partial',
            'reversal_status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'purchase_quantity' => 10.0,
            'purchase_unit' => 'kg',
            'base_quantity' => 10.0,
            'base_unit_cost' => 800.00,
            'unit_price' => 800.00,
            'subtotal' => 8000.00,
        ]);

        $batch = StockBatch::create([
            'purchase_item_id' => $item->id,
            'product_id' => $product->id,
            'batch_no' => 'BATCH-BEEF-01',
            'received_date' => '2026-11-13',
            'initial_quantity' => 10.0,
            'remaining_quantity' => 10.0,
            'unit_cost' => 800.00,
            'is_exhausted' => false,
        ]);

        // Kitchen consumes 6 kg. Remaining shelf stock is 4 kg.
        $this->consumptionService->recordConsumption(
            date: '2026-11-13',
            items: [['product_id' => $product->id, 'quantity_used' => 6.0]],
            userId: $this->admin->id,
            notes: 'Beef curry cooking'
        );

        $this->assertEquals(4.0, (float) $batch->fresh()->remaining_quantity);
        $this->assertEquals(4.0, (float) $product->fresh()->current_stock);

        // Attempting full void without allowPartialReturn is blocked
        try {
            $this->reversalService->voidPurchase($purchase, 'Cancel invoice', false);
            $this->fail('Should have thrown ValidationException');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('consumed', $e->getMessage());
        }

        // Performing partial return of remaining 4 kg with credit note
        $result = $this->reversalService->voidPurchase($purchase, 'Return 4kg intact beef to supplier', true);
        $this->assertTrue($result['success']);
        $this->assertEquals(3200.00, $result['returned_value']); // 4 kg @ ৳800 = ৳3200

        // Shelf stock for product is now 0 kg (deducted cleanly via StockLedger)
        $this->assertEquals(0.0, (float) $product->fresh()->current_stock);
        $this->assertTrue((bool) $batch->fresh()->is_exhausted);

        // Supplier due was ৳4000 unpaid, reduced by returned ৳3200 = ৳800 remaining due
        $this->assertEquals(800.00, (float) $supplier->fresh()->current_balance);
    }
}
