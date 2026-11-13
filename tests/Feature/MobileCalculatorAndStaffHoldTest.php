<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DailySale;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAttendance;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\SalaryPayment;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCalculatorAndStaffHoldTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected FinancialAccount $cashDrawer;
    protected EmployeeService $employeeService;
    protected StockLedgerService $stockLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Royal Nest Main Branch',
            'code' => 'RN-MAIN-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'email' => 'manager_'.uniqid().'@royalnest.com',
            'primary_role' => 'manager',
        ]);

        $this->cashDrawer = FinancialAccount::create([
            'name' => 'Cash Drawer',
            'type' => 'cash',
            'current_balance' => 50000.00,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->employeeService = app(EmployeeService::class);
        $this->stockLedgerService = app(StockLedgerService::class);
    }

    /**
     * 1. Test employee creation with department, held salary days and initial advance.
     */
    public function test_employee_creation_with_department_held_salary_and_initial_advance(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/employees', [
            'employee_id' => 'EMP-TEST-99',
            'name' => 'Monirul Islam',
            'phone' => '01711223344',
            'role' => 'Head Chef',
            'department' => 'kitchen',
            'tags' => ['kitchen', 'chef', 'senior'],
            'joining_date' => now()->toDateString(),
            'salary_type' => 'monthly',
            'agreed_salary' => 30000.00,
            'held_salary_days' => 3,
            'held_salary_amount' => 0.00,
            'initial_advance' => 5000.00,
            'payment_account_id' => $this->cashDrawer->id,
            'status' => 'active',
        ]);

        $response->assertSessionHas('success');

        $emp = Employee::where('employee_id', 'EMP-TEST-99')->first();
        $this->assertNotNull($emp);
        $this->assertEquals('kitchen', $emp->department);
        $this->assertEquals(3, $emp->held_salary_days);
        $this->assertEquals(30000.00, (float) $emp->agreed_salary);

        // Verify initial advance was created and cash drawer deducted
        $advance = EmployeeAdvance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($advance);
        $this->assertEquals(5000.00, (float) $advance->amount);
        $this->assertEquals(5000.00, (float) $advance->remaining_balance);

        // Cash drawer should be 50,000 - 5,000 = 45,000
        $this->cashDrawer->refresh();
        $this->assertEquals(45000.00, (float) $this->cashDrawer->current_balance);
    }

    /**
     * 2. Test Held Salary formula and Net Disbursable Salary reduction.
     */
    public function test_held_salary_deposit_formula_and_net_payable_reduction(): void
    {
        // Monthly worker: Agreed ৳30,000. 3 days held salary = (30000 / 30) * 3 = ৳3,000.
        $emp = Employee::create([
            'employee_id' => 'EMP-HOLD-01',
            'name' => 'Rahim Cook',
            'phone' => '01888999000',
            'role' => 'Line Cook',
            'department' => 'kitchen',
            'joining_date' => now()->startOfMonth()->toDateString(),
            'salary_type' => 'monthly',
            'agreed_salary' => 30000.00,
            'held_salary_days' => 3,
            'held_salary_amount' => 0.00,
            'status' => 'active',
            'is_active' => true,
        ]);

        // Present all 30 days
        $start = now()->startOfMonth();
        for ($i = 0; $i < 30; $i++) {
            EmployeeAttendance::create([
                'employee_id' => $emp->id,
                'attendance_date' => $start->copy()->addDays($i)->toDateString(),
                'status' => 'present',
                'created_by' => $this->user->id,
            ]);
        }

        $profile = $this->employeeService->getEmployeeProfile($emp->id, now()->format('Y-m'));

        // Held deposit = (30000 / 30) * 3 = 3000.00
        $this->assertEquals(3000.00, $profile['held_salary_deposit']);
        $this->assertEquals(3, $profile['held_salary_days']);

        // Accrued salary = 30000.00
        $this->assertEquals(30000.00, $profile['accrued_salary']);

        // Net Remaining Payable = 30000 - 0 (paid) - 3000 (held) = 27000.00
        $this->assertEquals(27000.00, $profile['remaining_payable']);
    }

    /**
     * 3. Test custom fixed held salary amount overrides days formula if provided.
     */
    public function test_custom_fixed_held_salary_amount_overrides_days_formula(): void
    {
        $emp = Employee::create([
            'employee_id' => 'EMP-FIXED-HOLD',
            'name' => 'Sujon Waiter',
            'phone' => '01999888777',
            'role' => 'Waiter',
            'department' => 'service',
            'joining_date' => now()->startOfMonth()->toDateString(),
            'salary_type' => 'monthly',
            'agreed_salary' => 18000.00,
            'held_salary_days' => 5,
            'held_salary_amount' => 2500.00, // Explicit custom fixed security deposit
            'status' => 'active',
            'is_active' => true,
        ]);

        // Present all 30 days
        $start = now()->startOfMonth();
        for ($i = 0; $i < 30; $i++) {
            EmployeeAttendance::create([
                'employee_id' => $emp->id,
                'attendance_date' => $start->copy()->addDays($i)->toDateString(),
                'status' => 'present',
                'created_by' => $this->user->id,
            ]);
        }

        $profile = $this->employeeService->getEmployeeProfile($emp->id, now()->format('Y-m'));

        // Custom amount 2500.00 takes precedence over (18000/30)*5 = 3000
        $this->assertEquals(2500.00, $profile['held_salary_deposit']);
        $this->assertEquals(18000.00 - 2500.00, $profile['remaining_payable']);
    }

    /**
     * 4. Test Unmarked Staff Attendance reminder counter.
     */
    public function test_unmarked_attendance_alert_computation(): void
    {
        $today = now()->toDateString();

        $emp1 = Employee::create([
            'employee_id' => 'EMP-UNM-1',
            'name' => 'Staff One',
            'role' => 'Server',
            'department' => 'service',
            'joining_date' => $today,
            'salary_type' => 'daily',
            'agreed_salary' => 600.00,
            'status' => 'active',
            'is_active' => true,
        ]);

        $emp2 = Employee::create([
            'employee_id' => 'EMP-UNM-2',
            'name' => 'Staff Two',
            'role' => 'Cleaner',
            'department' => 'cleaning',
            'joining_date' => $today,
            'salary_type' => 'daily',
            'agreed_salary' => 500.00,
            'status' => 'active',
            'is_active' => true,
        ]);

        // Neither staff has attendance today -> unmarked count = 2
        $unmarked = Employee::where('status', 'active')
            ->whereDoesntHave('attendances', fn ($q) => $q->where('attendance_date', $today))
            ->count();

        $this->assertEquals(2, $unmarked);

        // Mark emp1 attendance as present
        EmployeeAttendance::create([
            'employee_id' => $emp1->id,
            'attendance_date' => $today,
            'status' => 'present',
            'created_by' => $this->user->id,
        ]);

        $unmarkedAfterOne = Employee::where('status', 'active')
            ->whereDoesntHave('attendances', fn ($q) => $q->where('attendance_date', $today))
            ->count();

        $this->assertEquals(1, $unmarkedAfterOne);
    }

    /**
     * 5. Test Daily P&L Calculator screen rendering and atomic save with FIFO batch consumption.
     */
    public function test_daily_profit_calculator_screen_and_atomic_save(): void
    {
        $this->actingAs($this->user);

        $cat = Category::create([
            'name' => 'Meat & Poultry',
            'slug' => 'meat-poultry',
            'is_active' => true,
        ]);

        $beef = Product::create([
            'name' => 'Beef / গরুর মাংস',
            'code' => 'BEEF-01',
            'category_id' => $cat->id,
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 800.00,
            'current_stock' => 15.00,
            'is_active' => true,
        ]);

        // Create stock batch with unit cost ৳800
        $batch = StockBatch::create([
            'batch_no' => 'BATCH-BEEF-20261113',
            'product_id' => $beef->id,
            'purchase_id' => null,
            'supplier_id' => null,
            'initial_quantity' => 15.00,
            'remaining_quantity' => 15.00,
            'unit_cost' => 800.00,
            'received_date' => now()->toDateString(),
            'is_exhausted' => false,
        ]);

        // 1. Visit calculator screen
        $today = now()->toDateString();
        $response = $this->get('/calculator?date='.$today);
        $response->assertStatus(200);

        // 2. Submit Daily Usage and Sales: 4 kg Beef used today + ৳8,000 cash sales
        $postResponse = $this->post('/calculator', [
            'date' => $today,
            'sales' => [
                'total_sales' => 8000.00,
                'cash_sales' => 6000.00,
                'bkash_sales' => 2000.00,
                'nagad_sales' => 0.00,
                'card_bank_sales' => 0.00,
                'other_sales' => 0.00,
            ],
            'items' => [
                [
                    'product_id' => $beef->id,
                    'quantity_used' => 4.00,
                ],
            ],
            'notes' => 'Lunch and Dinner Beef Special',
        ]);

        $postResponse->assertSessionHas('success');

        // Verify product stock was decremented: 15 - 4 = 11 kg
        $beef->refresh();
        $this->assertEquals(11.00, (float) $beef->current_stock);

        // Verify batch was decremented: 15 - 4 = 11 kg
        $batch->refresh();
        $this->assertEquals(11.00, (float) $batch->remaining_quantity);

        // Verify DailySale was created/updated
        $sale = DailySale::whereDate('sales_date', $today)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(8000.00, (float) $sale->total_sales);
        $this->assertEquals(6000.00, (float) $sale->cash_sales);
        $this->assertEquals(2000.00, (float) $sale->bkash_sales);
    }

    /**
     * 6. Test fast worker salary payout and advance shortcut from Daily Cost Entry screen.
     */
    public function test_fast_worker_salary_payout_and_advance_from_cost_entry(): void
    {
        $this->actingAs($this->user);

        $emp = Employee::create([
            'employee_id' => 'EMP-DAILY-WAGE',
            'name' => 'Anwar Assistant Cook',
            'phone' => '01600112233',
            'role' => 'Assistant Cook',
            'department' => 'kitchen',
            'joining_date' => now()->startOfMonth()->toDateString(),
            'salary_type' => 'daily',
            'agreed_salary' => 700.00,
            'status' => 'active',
            'is_active' => true,
        ]);

        $initialCash = (float) $this->cashDrawer->current_balance;

        // 1. Record daily salary payout of ৳700 from /daily-costs
        $today = now()->toDateString();
        $payoutResponse = $this->post('/daily-costs', [
            'transaction_type' => 'salary_payment',
            'transaction_date' => $today,
            'employee_id' => $emp->id,
            'amount' => 700.00,
            'payment_account_id' => $this->cashDrawer->id,
            'notes' => 'Daily wage payout via shortcut',
        ]);

        $payoutResponse->assertSessionHas('success');

        // Cash drawer deducted: 50,000 - 700 = 49,300
        $this->cashDrawer->refresh();
        $this->assertEquals($initialCash - 700.00, (float) $this->cashDrawer->current_balance);

        // SalaryPayment record exists
        $payment = SalaryPayment::where('employee_id', $emp->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(700.00, (float) $payment->amount);

        // 2. Record staff advance of ৳1,000 from /daily-costs
        $advanceResponse = $this->post('/daily-costs', [
            'transaction_type' => 'salary_advance',
            'transaction_date' => $today,
            'employee_id' => $emp->id,
            'amount' => 1000.00,
            'payment_account_id' => $this->cashDrawer->id,
            'notes' => 'Emergency advance given',
        ]);

        $advanceResponse->assertSessionHas('success');

        // Cash drawer deducted: 49,300 - 1,000 = 48,300
        $this->cashDrawer->refresh();
        $this->assertEquals($initialCash - 1700.00, (float) $this->cashDrawer->current_balance);

        // EmployeeAdvance record exists
        $advance = EmployeeAdvance::where('employee_id', $emp->id)->latest('id')->first();
        $this->assertNotNull($advance);
        $this->assertEquals(1000.00, (float) $advance->amount);
        $this->assertEquals(1000.00, (float) $advance->remaining_balance);
    }
}

