<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FinancialAccount;
use App\Models\SalaryPayment;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * Employee Directory: Master list of all staff members.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');
        $date = $request->query('date', now()->toDateString());
        $month = substr($date, 0, 7);

        $query = Employee::with([
            'attendances' => fn($q) => $q->where('attendance_date', $date),
            'salaryPayments' => fn($q) => $q->where('month_year', $month),
            'advances' => fn($q) => $q->where('remaining_balance', '>', 0),
        ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')->get();
        $accounts = FinancialAccount::where('is_active', true)->orderBy('name')->get();

        $stats = [
            'total_staff' => Employee::count(),
            'active_staff' => Employee::where('status', 'active')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'total_advances' => Employee::withSum('advances as total_adv', 'remaining_balance')->get()->sum('total_adv'),
        ];

        $nextEmployeeId = $this->employeeService->generateNextEmployeeId();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'accounts' => $accounts,
            'stats' => $stats,
            'selectedDate' => $date,
            'nextEmployeeId' => $nextEmployeeId,
            'filters' => [
                'status' => $status,
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Individual Employee Profile (Personal, Attendance, Salary, Advances, Deductions).
     */
    public function profile(Request $request, Employee $employee): Response
    {
        $monthYear = $request->query('month', now()->format('Y-m'));
        $profileData = $this->employeeService->getEmployeeProfile($employee->id, $monthYear);
        $accounts = FinancialAccount::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Employees/Profile', [
            'profile' => $profileData,
            'accounts' => $accounts,
            'month' => $monthYear,
        ]);
    }

    /**
     * Bulk Attendance Entry View for team.
     */
    public function bulkAttendanceView(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());
        $employees = Employee::where('status', 'active')
            ->with(['attendances' => fn ($q) => $q->where('attendance_date', $date)])
            ->orderBy('name')
            ->get();

        return Inertia::render('Employees/BulkAttendance', [
            'employees' => $employees,
            'date' => $date,
        ]);
    }

    /**
     * Store bulk attendance entries.
     */
    public function storeBulkAttendance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.employee_id' => 'required|exists:employees,id',
            'records.*.status' => 'required|in:present,absent,half_day,leave',
            'records.*.notes' => 'nullable|string',
        ]);

        $this->employeeService->recordBulkAttendance($validated['date'], $validated['records']);

        return redirect()->route('employees.index')->with('success', 'Bulk attendance recorded successfully.');
    }

    /**
     * Create new employee profile with photos.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|string|max:50|unique:employees,employee_id',
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:50',
            'alternative_phone' => 'nullable|string|max:50',
            'nid_number' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'role' => 'required|string|max:100',
            'joining_date' => 'required|date',
            'salary_type' => 'required|in:monthly,daily,hourly',
            'agreed_salary' => 'required|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,on_leave,archived',
            'notes' => 'nullable|string',
            'photo' => 'nullable',
            'nid_photo' => 'nullable',
        ]);

        $employee = $this->employeeService->createEmployee($validated);

        return redirect()->back()->with('success', "Staff member {$employee->name} ({$employee->employee_id}) registered successfully.");
    }

    /**
     * Update employee profile.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:50',
            'alternative_phone' => 'nullable|string|max:50',
            'nid_number' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'role' => 'required|string|max:100',
            'joining_date' => 'required|date',
            'salary_type' => 'required|in:monthly,daily,hourly',
            'agreed_salary' => 'required|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,on_leave,archived',
            'notes' => 'nullable|string',
            'photo' => 'nullable',
            'nid_photo' => 'nullable',
        ]);

        $this->employeeService->updateEmployee($employee, $validated);

        return redirect()->back()->with('success', 'Staff profile updated.');
    }

    /**
     * Mark single employee attendance.
     */
    public function markAttendance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'status' => 'required|in:present,absent,half_day,leave',
            'notes' => 'nullable|string',
        ]);

        $this->employeeService->markAttendance(
            $validated['employee_id'],
            $validated['attendance_date'],
            $validated['status'],
            $validated['notes'] ?? null
        );

        return redirect()->back()->with('success', 'Attendance marked.');
    }

    /**
     * Issue an advance to an employee.
     */
    public function issueAdvance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'advance_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'required|exists:financial_accounts,id',
            'payment_method' => 'required|string|in:cash,bank,mfs,other',
            'reason' => 'nullable|string|max:255',
        ]);

        $userId = auth()->id() ?? 1;

        $advance = $this->employeeService->issueAdvance(
            employeeId: (int)$validated['employee_id'],
            amount: (float)$validated['amount'],
            advanceDate: $validated['advance_date'],
            paymentAccountId: (int)$validated['payment_account_id'],
            paymentMethod: $validated['payment_method'],
            reason: $validated['reason'] ?? null,
            userId: $userId
        );

        return redirect()->back()->with('success', "Staff advance voucher #{$advance->voucher_no} recorded.");
    }

    /**
     * Disburse salary with optional advance recovery.
     */
    public function paySalary(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:salary_payment,salary_advance',
            'amount' => 'required|numeric|min:0.01',
            'advance_deduction' => 'nullable|numeric|min:0',
            'payment_account_id' => 'required|exists:financial_accounts,id',
            'month_year' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $userId = auth()->id() ?? 1;

        $payment = $this->employeeService->disburseSalary(
            employeeId: (int)$validated['employee_id'],
            grossAmount: (float)$validated['amount'],
            paymentDate: $validated['payment_date'],
            paymentAccountId: (int)$validated['payment_account_id'],
            paymentType: $validated['payment_type'],
            monthYear: $validated['month_year'],
            advanceDeduction: (float)($validated['advance_deduction'] ?? 0),
            notes: $validated['notes'] ?? null,
            userId: $userId
        );

        return redirect()->back()->with('success', 'Salary payout recorded successfully.');
    }
}
