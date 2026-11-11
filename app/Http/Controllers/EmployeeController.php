<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\FinancialAccount;
use App\Models\SalaryPayment;
use App\Services\FinancialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected FinancialAccountService $accountService
    ) {}

    public function index(Request $request): Response
    {
        $date = $request->input('date', now()->toDateString());
        $month = substr($date, 0, 7);

        $employees = Employee::with([
            'attendances' => fn($q) => $q->where('attendance_date', $date),
            'salaryPayments' => fn($q) => $q->where('month_year', $month),
        ])->where('is_active', true)->get();

        $accounts = FinancialAccount::where('is_active', true)->get();

        $payments = SalaryPayment::with(['employee', 'paymentAccount', 'creator'])
            ->latest('payment_date')
            ->limit(50)
            ->get();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'accounts' => $accounts,
            'payments' => $payments,
            'selectedDate' => $date,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'role' => 'required|string|max:100',
            'joining_date' => 'required|date',
            'salary_type' => 'required|in:monthly,daily',
            'agreed_salary' => 'required|numeric|min:0',
        ]);

        Employee::create(array_merge($validated, ['is_active' => true]));

        return redirect()->back()->with('success', 'Employee profile created.');
    }

    public function markAttendance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'status' => 'required|in:present,absent,half_day,leave',
            'notes' => 'nullable|string',
        ]);

        EmployeeAttendance::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'attendance_date' => $validated['attendance_date'],
            ],
            [
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Attendance updated.');
    }

    public function paySalary(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:salary_payment,salary_advance',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'required|exists:financial_accounts,id',
            'month_year' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $payment = SalaryPayment::create([
            'employee_id' => $validated['employee_id'],
            'payment_date' => $validated['payment_date'],
            'payment_type' => $validated['payment_type'],
            'amount' => $validated['amount'],
            'payment_account_id' => $validated['payment_account_id'],
            'month_year' => $validated['month_year'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id() ?: 1,
        ]);

        // Withdraw from chosen cash/bank account
        $this->accountService->withdraw(
            accountId: (int)$validated['payment_account_id'],
            amount: (float)$validated['amount'],
            date: $validated['payment_date'],
            referenceType: SalaryPayment::class,
            referenceId: $payment->id,
            notes: "Salary payout for {$validated['month_year']}"
        );

        return redirect()->back()->with('success', 'Salary payment recorded. Cash account balance updated without duplicating P&L expense.');
    }
}

