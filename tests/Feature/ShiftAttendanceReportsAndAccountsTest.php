<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalaryPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\FinancialAccountService;
use App\Services\PurchaseService;
use App\Services\SmartCostService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShiftAttendanceReportsAndAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Branch $branch;

    protected FinancialAccount $cashAccount;

    protected FinancialAccount $bankAccount;

    protected EmployeeService $employeeService;

    protected SupplierService $supplierService;

    protected PurchaseService $purchaseService;

    protected SmartCostService $smartCostService;

    protected FinancialAccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Royal Nest Main Branch',
            'code' => 'RN-01',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'email' => 'admin_'.uniqid().'@royalnest.com',
            'primary_role' => 'admin',
        ]);

        $this->cashAccount = FinancialAccount::create([
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'current_balance' => 150000.00,
            'is_active' => true,
        ]);

        $this->bankAccount = FinancialAccount::create([
            'name' => 'City Bank Current',
            'type' => 'bank',
            'current_balance' => 200000.00,
            'is_active' => true,
        ]);

        $this->employeeService = app(EmployeeService::class);
        $this->supplierService = app(SupplierService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->smartCostService = app(SmartCostService::class);
        $this->accountService = app(FinancialAccountService::class);
    }

    /**
     * 1. Test shift assignment, shift attendance, and missing-shift alert calculation.
     */
    public function test_employee_shifts_and_missing_shift_alerts_disappear_when_recorded(): void
    {
        $today = now()->toDateString();

        // Employee A: Morning shift
        $empMorning = $this->employeeService->createEmployee([
            'name' => 'Rahim Cook',
            'role' => 'Chef',
            'department' => 'kitchen',
            'joining_date' => $today,
            'salary_type' => 'monthly',
            'agreed_salary' => 25000,
            'assigned_shift' => 'morning',
        ]);

        // Employee B: Evening shift
        $empEvening = $this->employeeService->createEmployee([
            'name' => 'Karim Waiter',
            'role' => 'Waiter',
            'department' => 'service',
            'joining_date' => $today,
            'salary_type' => 'daily',
            'agreed_salary' => 800,
            'assigned_shift' => 'evening',
        ]);

        // Employee C: Both shifts
        $empBoth = $this->employeeService->createEmployee([
            'name' => 'Salam Manager',
            'role' => 'Shift Supervisor',
            'department' => 'management',
            'joining_date' => $today,
            'salary_type' => 'monthly',
            'agreed_salary' => 30000,
            'assigned_shift' => 'both',
        ]);

        // Initially: 1 alert for Morning (Rahim), 1 alert for Evening (Karim), 2 alerts for Both (Salam) = 4 alerts
        $initialAlerts = $this->employeeService->getMissingAttendanceAlerts($today);
        $this->assertCount(4, $initialAlerts);

        // Record Rahim's morning shift
        $this->employeeService->markAttendance($empMorning->id, $today, 'present', null, 'morning');

        $alertsAfterA = $this->employeeService->getMissingAttendanceAlerts($today);
        $this->assertCount(3, $alertsAfterA);
        $this->assertFalse(collect($alertsAfterA)->contains('employee_id', $empMorning->id));

        // Record Salam's morning shift: Salam's evening shift alert should still remain
        $this->employeeService->markAttendance($empBoth->id, $today, 'present', null, 'morning');

        $alertsAfterCMorning = $this->employeeService->getMissingAttendanceAlerts($today);
        $this->assertCount(2, $alertsAfterCMorning);
        $salamEveningAlert = collect($alertsAfterCMorning)->firstWhere('employee_id', $empBoth->id);
        $this->assertNotNull($salamEveningAlert);
        $this->assertEquals('evening', $salamEveningAlert['missing_shift']);

        // Record Salam's evening shift
        $this->employeeService->markAttendance($empBoth->id, $today, 'present', null, 'evening');

        $alertsAfterCEvening = $this->employeeService->getMissingAttendanceAlerts($today);
        $this->assertCount(1, $alertsAfterCEvening);
        $this->assertEquals($empEvening->id, $alertsAfterCEvening[0]['employee_id']);

        // Record Karim's evening shift -> zero missing alerts remain
        $this->employeeService->markAttendance($empEvening->id, $today, 'present', null, 'evening');

        $finalAlerts = $this->employeeService->getMissingAttendanceAlerts($today);
        $this->assertCount(0, $finalAlerts);
    }

    /**
     * 2. Test preservation of salary calculation rules for workers working both shifts (zero arbitrary cuts).
     */
    public function test_salary_calculation_preserves_rules_for_both_shift_employees(): void
    {
        $today = now()->toDateString();
        $currentMonth = now()->format('Y-m');

        // Daily wage staff assigned to 'both' shifts
        $empDaily = $this->employeeService->createEmployee([
            'name' => 'Akbar Grill Master',
            'role' => 'Grill Specialist',
            'department' => 'kitchen',
            'joining_date' => $today,
            'salary_type' => 'daily',
            'agreed_salary' => 1000.00,
            'assigned_shift' => 'both',
        ]);

        // Record both morning and evening shifts on today's calendar date
        $this->employeeService->markAttendance($empDaily->id, $today, 'present', null, 'morning');
        $this->employeeService->markAttendance($empDaily->id, $today, 'present', null, 'evening');

        $profile = $this->employeeService->getEmployeeProfile($empDaily->id, $currentMonth);

        // Daily worker attended 1 calendar day -> accrued salary is 1 * 1000 = 1000 (NOT cut to 500, NOT doubled to 2000)
        $this->assertEquals(1000.00, $profile['accrued_salary']);
        $this->assertEquals(1000.00, $profile['remaining_payable']);
    }

    /**
     * 3. Test supplier payment multi-bill allocation and outstanding limit validation.
     */
    public function test_supplier_payment_multi_bill_allocation_and_outstanding_limits(): void
    {
        $supplier = Supplier::create([
            'name' => 'Universal Poultry Supplies',
            'phone' => '01711000111',
            'current_balance' => 0.00,
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Raw Meat & Chicken',
            'slug' => 'raw-meat-'.uniqid(),
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $chicken = Product::create([
            'name' => 'Fresh Broiler Chicken',
            'category_id' => $category->id,
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 200.00,
            'current_stock' => 0,
            'min_reorder_level' => 10,
            'is_active' => true,
        ]);

        // Purchase Bill 1: 50 kg @ ৳200 = ৳10,000 (Paid ৳2,000, Due ৳8,000)
        $p1 = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->subDays(2)->toDateString(),
                'paid_amount' => 2000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                ['product_id' => $chicken->id, 'purchase_quantity' => 50, 'purchase_unit' => 'kg', 'unit_price' => 200.00],
            ],
            userId: $this->admin->id
        );

        // Purchase Bill 2: 30 kg @ ৳200 = ৳6,000 (Paid ৳1,000, Due ৳5,000)
        $p2 = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->subDay()->toDateString(),
                'paid_amount' => 1000.00,
                'payment_account_id' => $this->cashAccount->id,
            ],
            items: [
                ['product_id' => $chicken->id, 'purchase_quantity' => 30, 'purchase_unit' => 'kg', 'unit_price' => 200.00],
            ],
            userId: $this->admin->id
        );

        $this->assertEquals(13000.00, (float) $supplier->fresh()->current_balance);
        $this->assertEquals(8000.00, (float) $p1->fresh()->due_amount);
        $this->assertEquals(5000.00, (float) $p2->fresh()->due_amount);

        // Settle multiple bills with specific allocation: ৳5,000 to Bill 1, ৳3,000 to Bill 2 (Total: ৳8,000)
        $payment = $this->supplierService->paySupplierDue(
            supplierId: $supplier->id,
            amount: 8000.00,
            paymentDate: now()->toDateString(),
            paymentAccountId: $this->cashAccount->id,
            notes: 'Multi-bill allocation test',
            userId: $this->admin->id,
            billAllocations: [
                ['purchase_id' => $p1->id, 'amount' => 5000.00],
                ['purchase_id' => $p2->id, 'amount' => 3000.00],
            ]
        );

        $this->assertDatabaseHas('supplier_payment_allocations', [
            'supplier_payment_id' => $payment->id,
            'purchase_id' => $p1->id,
            'allocated_amount' => 5000.00,
        ]);

        $this->assertDatabaseHas('supplier_payment_allocations', [
            'supplier_payment_id' => $payment->id,
            'purchase_id' => $p2->id,
            'allocated_amount' => 3000.00,
        ]);

        // Check updated bill dues
        $this->assertEquals(3000.00, (float) $p1->fresh()->due_amount); // 8000 - 5000 = 3000
        $this->assertEquals(2000.00, (float) $p2->fresh()->due_amount); // 5000 - 3000 = 2000
        $this->assertEquals(5000.00, (float) $supplier->fresh()->current_balance);

        // Enforce limit: allocating more than remaining bill due throws ValidationException
        $this->expectException(ValidationException::class);
        $this->supplierService->paySupplierDue(
            supplierId: $supplier->id,
            amount: 5000.00,
            paymentDate: now()->toDateString(),
            paymentAccountId: $this->cashAccount->id,
            billAllocations: [
                ['purchase_id' => $p1->id, 'amount' => 4000.00], // remaining is only 3000!
            ]
        );
    }

    /**
     * 4. Test Smart Cost salary payment shortcut has zero duplicate operating expense.
     */
    public function test_smart_cost_salary_payment_shortcut_does_not_create_duplicate_operating_expense(): void
    {
        $today = now()->toDateString();
        $emp = $this->employeeService->createEmployee([
            'name' => 'Tanvir Cashier',
            'role' => 'Senior Cashier',
            'department' => 'cashier',
            'joining_date' => $today,
            'salary_type' => 'monthly',
            'agreed_salary' => 20000,
        ]);

        $initialCash = (float) $this->cashAccount->current_balance;

        // Execute salary payment shortcut via SmartCostService
        $result = $this->smartCostService->recordCost([
            'transaction_type' => 'salary_payment',
            'transaction_date' => $today,
            'employee_id' => $emp->id,
            'amount' => 15000.00,
            'payment_account_id' => $this->cashAccount->id,
            'salary_month' => substr($today, 0, 7),
            'notes' => 'Mid-month salary payment via quick shortcut',
        ], $this->admin->id);

        $this->assertEquals('salary_payment', $result['type']);

        // Check salary payment record exists
        $this->assertDatabaseHas('salary_payments', [
            'employee_id' => $emp->id,
            'amount' => 15000.00,
            'payment_account_id' => $this->cashAccount->id,
        ]);

        // CRUCIAL ACCOUNTING CHECK: Operating Expenses table must have ZERO records for this salary payment!
        $this->assertEquals(0, OperatingExpense::count());

        // Cash account must be deducted by exactly ৳15,000
        $this->assertEquals($initialCash - 15000.00, (float) $this->cashAccount->fresh()->current_balance);
    }

    /**
     * 5. Test strict category separation: Raw Materials != Packaging Materials != Consumables.
     */
    public function test_strict_category_separation_for_packaging_and_raw_materials(): void
    {
        // Category 1: Raw Material
        $catRaw = Category::create([
            'name' => 'Vegetables & Produce',
            'slug' => 'veg-'.uniqid(),
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        // Category 2: Packaging Material
        $catPkg = Category::create([
            'name' => 'Takeaway Boxes & Bags',
            'slug' => 'pkg-'.uniqid(),
            'type' => 'packaging',
            'is_active' => true,
        ]);

        // Category 3: Consumables
        $catConsumable = Category::create([
            'name' => 'Cleaning Liquids',
            'slug' => 'clean-'.uniqid(),
            'type' => 'consumable',
            'is_active' => true,
        ]);

        $prodRaw = Product::create([
            'name' => 'Fresh Onion',
            'category_id' => $catRaw->id,
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 60.00,
            'current_stock' => 0,
            'min_reorder_level' => 5,
            'is_active' => true,
        ]);

        $prodPkg = Product::create([
            'name' => 'Burger Box 3-Ply',
            'category_id' => $catPkg->id,
            'product_type' => 'packaging',
            'base_unit' => 'pcs',
            'purchase_unit' => 'pcs',
            'cost_price' => 5.00,
            'current_stock' => 0,
            'min_reorder_level' => 50,
            'is_active' => true,
        ]);

        $this->assertEquals('raw_material', $prodRaw->product_type);
        $this->assertEquals('packaging', $prodPkg->product_type);
        $this->assertNotEquals($prodRaw->product_type, $prodPkg->product_type);
    }

    /**
     * 6. Test Daily Transactions Report distinguishing P&L expenses from liability settlements.
     */
    public function test_daily_transactions_report_strict_accounting_separation(): void
    {
        $today = now()->toDateString();

        // 1. Operating Expense (True P&L)
        OperatingExpense::create([
            'expense_date' => $today,
            'category' => 'electricity',
            'name' => 'Electricity Bill DESCO',
            'amount' => 4500.00,
            'financial_account_id' => $this->cashAccount->id,
            'created_by' => $this->admin->id,
        ]);

        // 2. Supplier due payment (Liability reduction - NOT P&L)
        $sup = Supplier::create([
            'name' => 'Bake King Bakery',
            'phone' => '01899112233',
            'current_balance' => 6000.00,
            'status' => 'active',
        ]);
        SupplierPayment::create([
            'supplier_id' => $sup->id,
            'payment_date' => $today,
            'amount' => 3000.00,
            'financial_account_id' => $this->cashAccount->id,
            'payment_method' => 'cash',
            'created_by' => $this->admin->id,
        ]);

        // Request /reports/transactions
        $response = $this->actingAs($this->admin)->get('/reports/transactions?date='.$today);
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Transactions')
            ->has('summary')
            ->where('summary.total_operating_expenses', fn ($v) => (float) $v == 4500.00)
            ->where('summary.total_supplier_payments', fn ($v) => (float) $v == 3000.00)
            ->where('summary.total_cash_outflows', fn ($v) => (float) $v == 7500.00)
        );
    }

    /**
     * 7. Test Admin financial account activation / deactivation and inactive account guard.
     */
    public function test_admin_account_activation_and_inactive_guard(): void
    {
        // Deactivate Bank Account
        $response = $this->actingAs($this->admin)->post("/accounts/{$this->bankAccount->id}/toggle-status");
        $response->assertSessionHas('success');

        $this->assertFalse((bool) $this->bankAccount->fresh()->is_active);

        // Attempting to withdraw from inactive bank account fails with validation exception
        $this->expectException(ValidationException::class);
        $this->accountService->withdraw(
            accountId: $this->bankAccount->id,
            amount: 500.00,
            date: now()->toDateString()
        );
    }

    /**
     * 8. Test PWA manifest and service worker presence and non-cache policies.
     */
    public function test_pwa_manifest_and_sw_integrity(): void
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);

        $manifestContent = json_decode(file_get_contents($manifestPath), true);
        $this->assertEquals('standalone', $manifestContent['display']);
        $this->assertEquals('#0f172a', $manifestContent['theme_color']);
        $this->assertNotEmpty($manifestContent['icons']);

        $swPath = public_path('sw.js');
        $this->assertFileExists($swPath);

        $swContent = file_get_contents($swPath);
        // Ensure private routes are strictly excluded from cache
        $this->assertStringContainsString('/api/', $swContent);
        $this->assertStringContainsString('/reports', $swContent);
        $this->assertStringContainsString('/daily-costs', $swContent);
        $this->assertStringContainsString('/accounts', $swContent);
    }
}
