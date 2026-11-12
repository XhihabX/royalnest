<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\SalaryDeduction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\PurchaseService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAndStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Branch $branch;

    protected FinancialAccount $cashDrawer;

    protected SupplierService $supplierService;

    protected EmployeeService $employeeService;

    protected PurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Royal Nest Main Branch',
            'code' => 'RN-01',
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
            'current_balance' => 100000.00,
            'is_active' => true,
        ]);

        $this->supplierService = app(SupplierService::class);
        $this->employeeService = app(EmployeeService::class);
        $this->purchaseService = app(PurchaseService::class);
    }

    public function test_supplier_directory_and_active_management_are_distinct(): void
    {
        // 1. Create a supplier in directory with status 'potential'
        $supplier = $this->supplierService->createDirectorySupplier([
            'name' => 'Kaptan Meat Market',
            'company_name' => 'Kaptan Agro Ltd',
            'contact_person' => 'Md. Faruk',
            'phone' => '01711223344',
            'category' => 'Meat & Poultry',
            'status' => 'potential',
            'opening_balance' => 0,
        ]);

        $this->assertEquals('potential', $supplier->status);

        // 2. Verify Directory lists it
        $directory = $this->supplierService->getDirectorySuppliers();
        $this->assertTrue($directory['suppliers']->contains('id', $supplier->id));

        // 3. Verify Active Suppliers does NOT list it
        $activeSuppliers = $this->supplierService->getActiveSuppliers();
        $this->assertFalse($activeSuppliers['suppliers']->contains('id', $supplier->id));

        // 4. Activate supplier
        $this->supplierService->setStatus($supplier, 'active');
        $activeSuppliersAfter = $this->supplierService->getActiveSuppliers();
        $this->assertTrue($activeSuppliersAfter['suppliers']->contains('id', $supplier->id));

        // 5. Add supplied product rate
        $prod = $this->supplierService->addSupplierProduct($supplier, [
            'product_name' => 'Beef Bone-In',
            'purchase_unit' => 'kg',
            'unit_price' => 750.00,
            'min_order_quantity' => 10,
        ]);
        $this->assertEquals(750.00, (float) $prod->unit_price);
    }

    public function test_supplier_purchases_and_due_payments_flow(): void
    {
        $supplier = $this->supplierService->createDirectorySupplier([
            'name' => 'Dhaka Fresh Spices',
            'phone' => '01899887766',
            'status' => 'active',
            'opening_balance' => 1000.00,
        ]);

        $this->assertEquals(1000.00, (float) $supplier->current_balance);

        $category = Category::create([
            'name' => 'Spices',
            'slug' => 'spices-'.uniqid(),
            'type' => 'raw_material',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chili Powder',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 400.00,
            'current_stock' => 0,
            'min_reorder_level' => 5,
        ]);

        // Purchase 10 kg @ ৳400 = ৳4000, paying ৳1500 from Cash Drawer
        $purchase = $this->purchaseService->confirmPurchase(
            [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'BILL-'.uniqid(),
                'purchase_date' => now()->toDateString(),
                'paid_amount' => 1500.00,
                'payment_account_id' => $this->cashDrawer->id,
            ],
            [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 10,
                    'purchase_unit' => 'kg',
                    'unit_price' => 400.00,
                ],
            ],
            $this->user->id
        );

        $this->cashDrawer->refresh();
        $supplier->refresh();

        // Cash drawer deducted ৳1500
        $this->assertEquals(98500.00, (float) $this->cashDrawer->current_balance);
        // Unpaid bill amount is ৳4000 - ৳1500 = ৳2500. Opening was ৳1000. Total due = ৳3500.
        $this->assertEquals(3500.00, (float) $supplier->current_balance);

        // Record a due payment voucher of ৳2000 from Cash Drawer
        $payment = $this->supplierService->paySupplierDue(
            supplierId: $supplier->id,
            amount: 2000.00,
            paymentDate: now()->toDateString(),
            paymentAccountId: $this->cashDrawer->id,
            paymentMethod: 'cash',
            notes: 'Paying vendor due',
            userId: $this->user->id
        );

        $this->cashDrawer->refresh();
        $supplier->refresh();

        $this->assertEquals(96500.00, (float) $this->cashDrawer->current_balance);
        $this->assertEquals(1500.00, (float) $supplier->current_balance);

        // Check statement ledger running balances
        $statement = $this->supplierService->getSupplierStatement($supplier->id);
        $this->assertEquals(1500.00, (float) $statement['current_balance']);
        $this->assertCount(2, $statement['statement_rows']); // 1 purchase bill + 1 payment voucher
    }

    public function test_employee_registration_attendance_and_advance_recovery(): void
    {
        // 1. Create employee -> auto ID generated
        $emp = $this->employeeService->createEmployee([
            'name' => 'Habib Master Chef',
            'phone' => '01755443322',
            'role' => 'Executive Chef',
            'joining_date' => now()->toDateString(),
            'salary_type' => 'monthly',
            'agreed_salary' => 25000.00,
            'status' => 'active',
        ]);

        $this->assertNotEmpty($emp->employee_id);
        $this->assertStringStartsWith('EMP-', $emp->employee_id);

        // 2. Mark attendance
        $today = now()->toDateString();
        $this->employeeService->markAttendance($emp->id, $today, 'present', 'On time');
        $this->employeeService->markAttendance($emp->id, $today, 'present', 'Updated note');

        // Check single record per date
        $attendanceCount = EmployeeAttendance::where('employee_id', $emp->id)
            ->where('attendance_date', $today)
            ->count();
        $this->assertEquals(1, $attendanceCount);

        // 3. Issue staff advance of ৳3000 from Cash Drawer
        $advance = $this->employeeService->issueAdvance(
            employeeId: $emp->id,
            amount: 3000.00,
            advanceDate: $today,
            paymentAccountId: $this->cashDrawer->id,
            paymentMethod: 'cash',
            reason: 'Medical expense advance',
            userId: $this->user->id
        );

        $this->cashDrawer->refresh();
        $this->assertEquals(97000.00, (float) $this->cashDrawer->current_balance);
        $this->assertEquals(3000.00, (float) $advance->remaining_balance);

        // 4. Disburse salary with ৳3000 advance recovery deduction
        // Gross salary ৳25,000 - ৳3,000 deduction = ৳22,000 net cash outflow
        $payment = $this->employeeService->disburseSalary(
            employeeId: $emp->id,
            grossAmount: 25000.00,
            paymentDate: $today,
            paymentAccountId: $this->cashDrawer->id,
            paymentType: 'salary_payment',
            monthYear: substr($today, 0, 7),
            advanceDeduction: 3000.00,
            notes: 'Monthly salary disbursal',
            userId: $this->user->id
        );

        $this->cashDrawer->refresh();
        $advance->refresh();

        // Cash drawer should be 97000 - 22000 = 75000
        $this->assertEquals(75000.00, (float) $this->cashDrawer->current_balance);
        // Advance should be settled (remaining 0)
        $this->assertEquals(0.00, (float) $advance->remaining_balance);
        $this->assertEquals('settled', $advance->status);

        // Verify salary deduction record created
        $deduction = SalaryDeduction::where('employee_id', $emp->id)->first();
        $this->assertNotNull($deduction);
        $this->assertEquals(3000.00, (float) $deduction->amount);
    }
}
