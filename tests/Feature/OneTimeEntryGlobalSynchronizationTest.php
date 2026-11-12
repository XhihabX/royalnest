<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\SalaryPayment;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\CashReconciliationService;
use App\Services\EmployeeService;
use App\Services\ProfitLossService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneTimeEntryGlobalSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected FinancialAccount $cashAccount;

    protected FinancialAccount $bkashAccount;

    protected Category $vegetableCategory;

    protected Supplier $supplier;

    protected Employee $employee;

    protected Product $tomato;

    protected SupplierService $supplierService;

    protected EmployeeService $employeeService;

    protected CashReconciliationService $cashReconciliationService;

    protected ProfitLossService $profitLossService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'cashier_'.uniqid().'@royalnest.com',
            'primary_role' => 'cashier',
            'is_active' => true,
        ]);

        $this->supplierService = app(SupplierService::class);
        $this->employeeService = app(EmployeeService::class);
        $this->cashReconciliationService = app(CashReconciliationService::class);
        $this->profitLossService = app(ProfitLossService::class);

        // 1. Cash Drawer Account
        $this->cashAccount = FinancialAccount::create([
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);

        // 2. Mobile Wallet
        $this->bkashAccount = FinancialAccount::create([
            'name' => 'bKash Merchant',
            'type' => 'bkash',
            'current_balance' => 15000.00,
            'is_active' => true,
        ]);

        // 3. Category & Product
        $this->vegetableCategory = Category::create([
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $this->tomato = Product::create([
            'category_id' => $this->vegetableCategory->id,
            'name' => 'Fresh Tomato (টমেটো)',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 5.0,
            'cost_price' => 50.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $this->tomato->id,
            'purchase_unit' => 'kg',
            'multiplier_to_base' => 1.0,
        ]);

        // 4. Supplier
        $this->supplier = Supplier::create([
            'name' => 'Bismillah Vegetable Supply',
            'company_name' => 'Bismillah Enterprise',
            'phone' => '01700999888',
            'status' => 'active',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        // 5. Employee
        $this->employee = Employee::create([
            'employee_id' => 'EMP-SYN-001',
            'name' => 'Hasan Ali',
            'phone' => '01911222333',
            'role' => 'Sous Chef',
            'salary_type' => 'monthly',
            'agreed_salary' => 18000.00,
            'joining_date' => '2026-01-01',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    /**
     * ACCEPTANCE TEST 1: Inventory Purchase
     * ONE purchase entry must automatically update:
     * - Inventory stock
     * - Stock ledger
     * - Supplier purchase history
     * - Supplier outstanding balance
     * - Cash/bank/bKash/Nagad account (according to actual amount paid)
     * - Purchase and financial reports
     * Without requiring a second manual entry anywhere!
     */
    public function test_acceptance_one_time_supplier_purchase_updates_all_modules_without_second_entry(): void
    {
        $today = now()->toDateString();

        // One-time entry from Daily Cost Entry screen:
        // Purchase 20kg tomato @ 60 = ৳1,200 total. Paid ৳500 from cash drawer, ৳700 due.
        $payload = [
            'transaction_type' => 'purchase',
            'transaction_date' => $today,
            'product_id' => $this->tomato->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 20,
            'unit' => 'kg',
            'unit_price' => 60.00,
            'amount' => 1200.00,
            'paid_amount' => 500.00,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'Morning fresh tomato delivery',
            'idempotency_key' => 'purchase_syn_'.uniqid(),
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertRedirect();

        // 1. Inventory Stock updated (5 initial + 20 purchased = 25kg)
        $this->tomato->refresh();
        $this->assertEquals(25.0, (float) $this->tomato->current_stock);

        // 2. Stock Ledger perpetual record created
        $ledgerEntry = StockLedger::where('product_id', $this->tomato->id)
            ->where('movement_type', 'purchase_receipt')
            ->latest('id')
            ->first();
        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(20.0, (float) $ledgerEntry->quantity_in);
        $this->assertEquals(25.0, (float) $ledgerEntry->balance_after);
        $this->assertEquals(Purchase::class, $ledgerEntry->reference_type);

        // 3. Supplier Purchase History & Unique Invoice ID
        $purchase = Purchase::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($purchase);
        $this->assertNotEmpty($purchase->invoice_no);
        $this->assertEquals(1200.00, (float) $purchase->total_amount);
        $this->assertEquals(500.00, (float) $purchase->paid_amount);

        // 4. Supplier Outstanding Balance (0 + 1200 - 500 = ৳700 due)
        $this->supplier->refresh();
        $this->assertEquals(700.00, (float) $this->supplier->current_balance);

        // 5. Supplier Profile & Statement
        $statement = $this->supplierService->getSupplierStatement($this->supplier->id);
        $this->assertEquals(700.00, $statement['supplier']->current_balance);
        $this->assertCount(1, $statement['statement_rows']);
        $this->assertEquals('purchase_bill', $statement['statement_rows'][0]['type']);
        $this->assertEquals(1200.00, $statement['statement_rows'][0]['bill_amount']);
        $this->assertEquals(500.00, $statement['statement_rows'][0]['paid_amount']);
        $this->assertEquals(700.00, $statement['statement_rows'][0]['remaining_bill_due']);

        // 6. Financial Account & Ledger (20000 initial - 500 paid = ৳19500)
        $this->cashAccount->refresh();
        $this->assertEquals(19500.00, (float) $this->cashAccount->current_balance);
        $this->assertDatabaseHas('account_transactions', [
            'financial_account_id' => $this->cashAccount->id,
            'type' => 'withdrawal',
            'amount' => 500.00,
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
        ]);

        // 7. Daily Cash Reconciliation (includes ৳500 cash payment automatically)
        $recon = $this->cashReconciliationService->getReconciliationData($today);
        $this->assertEquals(500.00, (float) $recon['today_cash_payments']);
        $this->assertCount(1, $recon['payments_breakdown']);
    }

    /**
     * ACCEPTANCE TEST 2: Supplier Payment
     * ONE payment entry must automatically update:
     * - Supplier payment history
     * - Supplier outstanding payable
     * - Selected financial account balance & ledger
     * - Daily cash reconciliation (only when cash was used)
     * - Relevant reports
     * Without duplicate generic operating expense!
     */
    public function test_acceptance_one_time_supplier_payment_updates_all_modules_without_second_entry(): void
    {
        $today = now()->toDateString();

        // Simulate an existing supplier due of ৳5,000
        $this->supplier->update([
            'current_balance' => 5000.00,
        ]);

        $initialOperatingExpenseCount = OperatingExpense::count();

        // One-time entry from Daily Cost Entry screen:
        // Payout ৳3,000 to clear supplier due from cash drawer
        $payload = [
            'transaction_type' => 'supplier_payment',
            'transaction_date' => $today,
            'supplier_id' => $this->supplier->id,
            'amount' => 3000.00,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'Partial clearance of vegetable due',
            'idempotency_key' => 'supplier_pay_syn_'.uniqid(),
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertRedirect();

        // 1. Supplier Payment History & Unique Voucher
        $payment = SupplierPayment::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertNotEmpty($payment->voucher_no);
        $this->assertEquals(3000.00, (float) $payment->amount);

        // 2. Supplier Outstanding Payable (5000 - 3000 = ৳2,000 remaining)
        $this->supplier->refresh();
        $this->assertEquals(2000.00, (float) $this->supplier->current_balance);

        // 3. Supplier Profile & Statement
        $statement = $this->supplierService->getSupplierStatement($this->supplier->id);
        $this->assertEquals(2000.00, $statement['supplier']->current_balance);
        $this->assertCount(1, $statement['statement_rows']);
        $this->assertEquals('payment_voucher', $statement['statement_rows'][0]['type']);
        $this->assertEquals(3000.00, $statement['statement_rows'][0]['paid_amount']);

        // 4. Financial Account & Ledger (20000 - 3000 = ৳17,000)
        $this->cashAccount->refresh();
        $this->assertEquals(17000.00, (float) $this->cashAccount->current_balance);
        $this->assertDatabaseHas('account_transactions', [
            'financial_account_id' => $this->cashAccount->id,
            'type' => 'withdrawal',
            'amount' => 3000.00,
            'reference_type' => SupplierPayment::class,
            'reference_id' => $payment->id,
        ]);

        // 5. Daily Cash Reconciliation
        $recon = $this->cashReconciliationService->getReconciliationData($today);
        $this->assertEquals(3000.00, (float) $recon['today_cash_payments']);

        // 6. Zero Duplicate Expense in P&L
        $this->assertEquals($initialOperatingExpenseCount, OperatingExpense::count());
    }

    /**
     * ACCEPTANCE TEST 3: Staff Salary Payment
     * ONE salary payment entry must automatically update:
     * - Employee salary payment history
     * - Employee outstanding salary in staff profile
     * - Selected financial account balance & ledger
     * - Daily cash reconciliation
     * Without duplicate expense!
     */
    public function test_acceptance_one_time_employee_salary_payment_updates_all_modules_without_second_entry(): void
    {
        $today = now()->toDateString();
        $currentMonth = date('Y-m', strtotime($today));

        $initialOperatingExpenseCount = OperatingExpense::count();

        // One-time entry from Daily Cost Entry screen:
        // Disburse ৳10,000 salary to Hasan Ali from cash drawer
        $payload = [
            'transaction_type' => 'salary_payment',
            'transaction_date' => $today,
            'employee_id' => $this->employee->id,
            'amount' => 10000.00,
            'salary_month' => $currentMonth,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'November partial salary payout',
            'idempotency_key' => 'sal_pay_syn_'.uniqid(),
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertRedirect();

        // 1. Employee Salary Payment History
        $payment = SalaryPayment::where('employee_id', $this->employee->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertEquals('salary_payment', $payment->payment_type);
        $this->assertEquals(10000.00, (float) $payment->amount);
        $this->assertEquals($currentMonth, $payment->month_year);

        // 2. Employee Profile (Agreed 18000 - 10000 paid = ৳8,000 remaining payable)
        $profile = $this->employeeService->getEmployeeProfile($this->employee->id, $currentMonth);
        $this->assertEquals(10000.00, (float) $profile['paid_this_month']);
        $this->assertEquals(8000.00, (float) $profile['remaining_payable']);
        $this->assertCount(1, $profile['salary_payments']);

        // 3. Financial Account & Ledger (20000 - 10000 = ৳10,000)
        $this->cashAccount->refresh();
        $this->assertEquals(10000.00, (float) $this->cashAccount->current_balance);
        $this->assertDatabaseHas('account_transactions', [
            'financial_account_id' => $this->cashAccount->id,
            'type' => 'withdrawal',
            'amount' => 10000.00,
            'reference_type' => SalaryPayment::class,
            'reference_id' => $payment->id,
        ]);

        // 4. Daily Cash Reconciliation
        $recon = $this->cashReconciliationService->getReconciliationData($today);
        $this->assertEquals(10000.00, (float) $recon['today_cash_payments']);

        // 5. Zero Duplicate Operating Expense
        $this->assertEquals($initialOperatingExpenseCount, OperatingExpense::count());
    }

    /**
     * ACCEPTANCE TEST 4: Staff Advance
     * ONE advance entry must automatically update:
     * - Employee advance history
     * - Employee advance balance
     * - Selected financial account balance & ledger
     * - Daily cash reconciliation
     * Advance must NOT be recognized as an operating expense!
     */
    public function test_acceptance_one_time_employee_advance_updates_all_modules_without_second_entry(): void
    {
        $today = now()->toDateString();
        $currentMonth = date('Y-m', strtotime($today));

        $initialOperatingExpenseCount = OperatingExpense::count();

        // One-time entry from Daily Cost Entry screen:
        // Issue ৳4,000 cash advance to Hasan Ali
        $payload = [
            'transaction_type' => 'salary_advance',
            'transaction_date' => $today,
            'employee_id' => $this->employee->id,
            'amount' => 4000.00,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'Emergency advance for family medical',
            'idempotency_key' => 'adv_syn_'.uniqid(),
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertRedirect();

        // 1. Employee Advance History & Unique Voucher
        $advance = EmployeeAdvance::where('employee_id', $this->employee->id)->latest('id')->first();
        $this->assertNotNull($advance);
        $this->assertNotEmpty($advance->voucher_no);
        $this->assertEquals(4000.00, (float) $advance->amount);
        $this->assertEquals(4000.00, (float) $advance->remaining_balance);

        // 2. Employee Profile Advance Balance
        $profile = $this->employeeService->getEmployeeProfile($this->employee->id, $currentMonth);
        $this->assertEquals(4000.00, (float) $profile['total_advances_taken']);
        $this->assertEquals(4000.00, (float) $profile['outstanding_advances']);
        $this->assertCount(1, $profile['advances']);

        // 3. Financial Account & Ledger (20000 - 4000 = ৳16,000)
        $this->cashAccount->refresh();
        $this->assertEquals(16000.00, (float) $this->cashAccount->current_balance);
        $this->assertDatabaseHas('account_transactions', [
            'financial_account_id' => $this->cashAccount->id,
            'type' => 'withdrawal',
            'amount' => 4000.00,
            'reference_type' => EmployeeAdvance::class,
            'reference_id' => $advance->id,
        ]);

        // 4. Daily Cash Reconciliation
        $recon = $this->cashReconciliationService->getReconciliationData($today);
        $this->assertEquals(4000.00, (float) $recon['today_cash_payments']);

        // 5. Zero Operating Expense Distortion in P&L
        $this->assertEquals($initialOperatingExpenseCount, OperatingExpense::count());
    }

    /**
     * Non-Negotiable Rule 6: Prevent duplicate submissions and accidental double processing.
     */
    public function test_duplicate_submission_protection_blocks_accidental_double_processing(): void
    {
        $idempotencyKey = 'fixed_test_key_12345';

        $payload = [
            'transaction_type' => 'expense',
            'transaction_date' => now()->toDateString(),
            'expense_category' => 'cleaning',
            'name' => 'Floor Disinfectant',
            'amount' => 800.00,
            'payment_account_id' => $this->cashAccount->id,
            'idempotency_key' => $idempotencyKey,
        ];

        // First click: Succeeds
        $first = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $first->assertSessionHas('success');
        $this->assertEquals(1, OperatingExpense::where('category', 'cleaning')->where('amount', 800.00)->count());

        // Second click within seconds: Blocked by duplicate submission lock
        $second = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $second->assertSessionHas('warning');

        // Still exactly 1 record exists!
        $this->assertEquals(1, OperatingExpense::where('category', 'cleaning')->where('amount', 800.00)->count());
        $this->cashAccount->refresh();
        // Balance reduced exactly once (20000 - 800 = 19200)
        $this->assertEquals(19200.00, (float) $this->cashAccount->current_balance);
    }

    /**
     * Non-Negotiable Rule 7: If an operation fails, roll back all related changes atomically.
     */
    public function test_atomic_rollback_on_failure_ensures_zero_partial_records(): void
    {
        $initialStock = (float) $this->tomato->current_stock;
        $initialBalance = (float) $this->cashAccount->current_balance;
        $initialPurchases = Purchase::count();
        $initialLedger = StockLedger::count();

        // Attempt invalid consumption where quantity requested exceeds current stock
        $payload = [
            'transaction_type' => 'consumption',
            'transaction_date' => now()->toDateString(),
            'product_id' => $this->tomato->id,
            'quantity' => 999.0, // Exceeds available stock
            'unit' => 'kg',
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertSessionHasErrors(['items']);

        // Assert all modules remained completely untouched
        $this->tomato->refresh();
        $this->assertEquals($initialStock, (float) $this->tomato->current_stock);
        $this->assertEquals($initialBalance, (float) $this->cashAccount->fresh()->current_balance);
        $this->assertEquals($initialPurchases, Purchase::count());
        $this->assertEquals($initialLedger, StockLedger::count());
    }
}
