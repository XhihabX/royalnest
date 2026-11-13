<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAttendance;
use App\Models\SalaryDeduction;
use App\Models\SalaryPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(
        protected FinancialAccountService $financialAccountService
    ) {}

    /**
     * Auto-generate next unique Employee ID (e.g. EMP-001, EMP-002).
     */
    public function generateNextEmployeeId(): string
    {
        $lastEmp = Employee::whereNotNull('employee_id')
            ->orderBy('id', 'desc')
            ->first();

        if (! $lastEmp || ! preg_match('/EMP-(\d+)/', $lastEmp->employee_id, $matches)) {
            $count = Employee::count();

            return 'EMP-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
        }

        $nextNum = ((int) $matches[1]) + 1;

        return 'EMP-'.str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create an employee profile with camera photos and auto-generated ID.
     */
    public function createEmployee(array $data): Employee
    {
        $employeeId = ! empty($data['employee_id']) ? trim($data['employee_id']) : $this->generateNextEmployeeId();

        $photoPath = $this->saveImageFileOrBase64($data['photo'] ?? null, 'staff');
        $nidPhotoPath = $this->saveImageFileOrBase64($data['nid_photo'] ?? null, 'staff/nid');

        $emp = Employee::create([
            'employee_id' => $employeeId,
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'nid_number' => $data['nid_number'] ?? null,
            'photo_path' => $photoPath,
            'nid_photo_path' => $nidPhotoPath,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'address' => $data['address'] ?? null,
            'role' => trim($data['role']),
            'department' => $data['department'] ?? 'kitchen',
            'tags' => is_array($data['tags'] ?? null) ? implode(', ', $data['tags']) : ($data['tags'] ?? null),
            'joining_date' => $data['joining_date'] ?? now()->toDateString(),
            'salary_type' => $data['salary_type'] ?? 'monthly',
            'agreed_salary' => (float) $data['agreed_salary'],
            'held_salary_days' => isset($data['held_salary_days']) ? (int) $data['held_salary_days'] : 0,
            'held_salary_amount' => isset($data['held_salary_amount']) ? (float) $data['held_salary_amount'] : 0.00,
            'hourly_rate' => isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null,
            'assigned_shift' => $data['assigned_shift'] ?? 'morning',
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'is_active' => ($data['status'] ?? 'active') === 'active',
        ]);

        if (! empty($data['initial_advance']) && (float) $data['initial_advance'] > 0) {
            $this->issueAdvance(
                employeeId: $emp->id,
                amount: (float) $data['initial_advance'],
                advanceDate: $emp->joining_date ? $emp->joining_date->toDateString() : now()->toDateString(),
                paymentAccountId: ! empty($data['payment_account_id']) ? (int) $data['payment_account_id'] : null,
                paymentMethod: 'cash',
                reason: 'Initial job joining advance',
                userId: auth()->id() ?: 1
            );
        }

        return $emp;
    }

    /**
     * Update an employee's profile and photos.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $updateData = [
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'nid_number' => $data['nid_number'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'address' => $data['address'] ?? null,
            'role' => trim($data['role']),
            'department' => $data['department'] ?? $employee->department ?? 'kitchen',
            'tags' => isset($data['tags']) ? (is_array($data['tags']) ? implode(', ', $data['tags']) : $data['tags']) : $employee->tags,
            'joining_date' => $data['joining_date'] ?? $employee->joining_date,
            'salary_type' => $data['salary_type'] ?? $employee->salary_type,
            'agreed_salary' => (float) $data['agreed_salary'],
            'held_salary_days' => isset($data['held_salary_days']) ? (int) $data['held_salary_days'] : (int) ($employee->held_salary_days ?? 0),
            'held_salary_amount' => isset($data['held_salary_amount']) ? (float) $data['held_salary_amount'] : (float) ($employee->held_salary_amount ?? 0.00),
            'hourly_rate' => isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : $employee->hourly_rate,
            'assigned_shift' => $data['assigned_shift'] ?? $employee->assigned_shift ?? 'morning',
            'status' => $data['status'] ?? $employee->status,
            'notes' => $data['notes'] ?? null,
            'is_active' => ($data['status'] ?? $employee->status) === 'active',
        ];

        if (! empty($data['photo'])) {
            $updateData['photo_path'] = $this->saveImageFileOrBase64($data['photo'], 'staff');
        }

        if (! empty($data['nid_photo'])) {
            $updateData['nid_photo_path'] = $this->saveImageFileOrBase64($data['nid_photo'], 'staff/nid');
        }

        $employee->update($updateData);

        return $employee;
    }

    /**
     * Mark daily attendance for an individual employee with optional shift.
     */
    public function markAttendance(int $employeeId, string $date, string $status, ?string $notes = null, ?string $shift = null): EmployeeAttendance
    {
        return EmployeeAttendance::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'attendance_date' => $date,
                'shift' => $shift,
            ],
            [
                'status' => $status,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Bulk mark attendance for all employees on a given date with shift support.
     */
    public function recordBulkAttendance(string $date, array $records): void
    {
        DB::transaction(function () use ($date, $records) {
            foreach ($records as $rec) {
                EmployeeAttendance::updateOrCreate(
                    [
                        'employee_id' => (int) $rec['employee_id'],
                        'attendance_date' => $date,
                        'shift' => $rec['shift'] ?? null,
                    ],
                    [
                        'status' => $rec['status'],
                        'notes' => $rec['notes'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Get missing attendance alerts for all active employees for a given date.
     * Evaluates employee assigned_shift (morning, evening, both) and checks if recorded.
     * Automatically clears as soon as the shift attendance is saved.
     */
    public function getMissingAttendanceAlerts(string $date): array
    {
        $activeEmployees = Employee::where('status', 'active')
            ->with(['attendances' => fn ($q) => $q->where('attendance_date', $date)])
            ->orderBy('name')
            ->get();

        $alerts = [];

        foreach ($activeEmployees as $emp) {
            $shiftPref = $emp->assigned_shift ?? 'morning';
            $recordedShifts = $emp->attendances->pluck('shift')->all();
            $hasLegacyNull = in_array(null, $recordedShifts, true);

            if ($hasLegacyNull) {
                // Legacy records with null shift satisfy attendance for the day
                continue;
            }

            if ($shiftPref === 'morning' || $shiftPref === 'both') {
                if (! in_array('morning', $recordedShifts, true)) {
                    $alerts[] = [
                        'employee_id' => $emp->id,
                        'employee_code' => $emp->employee_id,
                        'name' => $emp->name,
                        'role' => $emp->role,
                        'department' => $emp->department,
                        'assigned_shift' => $shiftPref,
                        'missing_shift' => 'morning',
                        'shift_label' => 'Morning Shift',
                    ];
                }
            }

            if ($shiftPref === 'evening' || $shiftPref === 'both') {
                if (! in_array('evening', $recordedShifts, true)) {
                    $alerts[] = [
                        'employee_id' => $emp->id,
                        'employee_code' => $emp->employee_id,
                        'name' => $emp->name,
                        'role' => $emp->role,
                        'department' => $emp->department,
                        'assigned_shift' => $shiftPref,
                        'missing_shift' => 'evening',
                        'shift_label' => 'Evening / Night Shift',
                    ];
                }
            }
        }

        return $alerts;
    }

    /**
     * Issue an advance to an employee (creates cash outflow and tracks advance balance).
     */
    public function issueAdvance(
        int $employeeId,
        float $amount,
        string $advanceDate,
        ?int $paymentAccountId,
        string $paymentMethod = 'cash',
        ?string $reason = null,
        int $userId = 1
    ): EmployeeAdvance {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Advance amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $employeeId,
            $amount,
            $advanceDate,
            $paymentAccountId,
            $paymentMethod,
            $reason,
            $userId
        ) {
            $employee = Employee::findOrFail($employeeId);
            $voucherNo = 'EADV-'.date('Ymd', strtotime($advanceDate)).'-'.strtoupper(substr(uniqid(), -5));

            $advance = EmployeeAdvance::create([
                'employee_id' => $employee->id,
                'advance_date' => $advanceDate,
                'amount' => $amount,
                'payment_account_id' => $paymentAccountId,
                'payment_method' => $paymentMethod,
                'voucher_no' => $voucherNo,
                'reason' => $reason,
                'remaining_balance' => $amount,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            if ($paymentAccountId) {
                $this->financialAccountService->withdraw(
                    accountId: $paymentAccountId,
                    amount: $amount,
                    date: $advanceDate,
                    referenceType: EmployeeAdvance::class,
                    referenceId: $advance->id,
                    userId: $userId,
                    notes: "Staff advance to {$employee->name} (Voucher #{$voucherNo})"
                );
            }

            return $advance;
        });
    }

    /**
     * Disburse salary with optional advance recovery.
     * Net payout = Disbursed Cash = (Gross Payout Amount - Advance Deduction).
     */
    public function disburseSalary(
        int $employeeId,
        float $grossAmount,
        string $paymentDate,
        int $paymentAccountId,
        string $paymentType,
        string $monthYear,
        float $advanceDeduction = 0.00,
        ?string $notes = null,
        int $userId = 1
    ): SalaryPayment {
        if ($grossAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Salary payout amount must be greater than zero.',
            ]);
        }

        $netCashPaid = round($grossAmount - $advanceDeduction, 2);
        if ($netCashPaid < 0) {
            throw ValidationException::withMessages([
                'advance_deduction' => 'Advance deduction cannot exceed the payout amount.',
            ]);
        }

        return DB::transaction(function () use (
            $employeeId,
            $grossAmount,
            $netCashPaid,
            $advanceDeduction,
            $paymentDate,
            $paymentAccountId,
            $paymentType,
            $monthYear,
            $notes,
            $userId
        ) {
            $employee = Employee::findOrFail($employeeId);

            $payment = SalaryPayment::create([
                'employee_id' => $employee->id,
                'payment_date' => $paymentDate,
                'payment_type' => $paymentType,
                'amount' => $grossAmount,
                'payment_account_id' => $paymentAccountId,
                'month_year' => $monthYear,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            // If advance deduction applied, recover from active advances
            if ($advanceDeduction > 0) {
                $remainingToDeduct = $advanceDeduction;
                $activeAdvances = EmployeeAdvance::where('employee_id', $employee->id)
                    ->where('remaining_balance', '>', 0)
                    ->orderBy('advance_date', 'asc')
                    ->get();

                foreach ($activeAdvances as $adv) {
                    if ($remainingToDeduct <= 0) {
                        break;
                    }

                    $deductFromThis = min((float) $adv->remaining_balance, $remainingToDeduct);
                    $adv->remaining_balance = round((float) $adv->remaining_balance - $deductFromThis, 2);
                    $adv->status = $adv->remaining_balance == 0 ? 'settled' : 'partially_deducted';
                    $adv->save();

                    SalaryDeduction::create([
                        'employee_id' => $employee->id,
                        'salary_payment_id' => $payment->id,
                        'employee_advance_id' => $adv->id,
                        'deduction_date' => $paymentDate,
                        'amount' => $deductFromThis,
                        'deduction_type' => 'advance_recovery',
                        'notes' => "Advance recovery against voucher #{$adv->voucher_no}",
                        'created_by' => $userId,
                    ]);

                    $remainingToDeduct -= $deductFromThis;
                }
            }

            // Deduct actual net cash paid from financial account
            if ($netCashPaid > 0) {
                $this->financialAccountService->withdraw(
                    accountId: $paymentAccountId,
                    amount: $netCashPaid,
                    date: $paymentDate,
                    referenceType: SalaryPayment::class,
                    referenceId: $payment->id,
                    userId: $userId,
                    notes: "Salary disbursal to {$employee->name} for {$monthYear} (Net Paid: ৳{$netCashPaid})"
                );
            }

            return $payment;
        });
    }

    /**
     * Get detailed financial & attendance summary for an individual employee profile.
     */
    public function getEmployeeProfile(int $employeeId, ?string $monthYear = null): array
    {
        $employee = Employee::with([
            'attendances' => fn ($q) => $q->orderBy('attendance_date', 'desc'),
            'salaryPayments' => fn ($q) => $q->with('account')->orderBy('payment_date', 'desc'),
            'advances' => fn ($q) => $q->with('account')->orderBy('advance_date', 'desc'),
            'deductions' => fn ($q) => $q->orderBy('deduction_date', 'desc'),
        ])->findOrFail($employeeId);

        $currentMonth = $monthYear ?: now()->format('Y-m');

        // Attendance stats for current month
        $monthAttendances = $employee->attendances->filter(fn ($a) => str_starts_with($a->attendance_date->toDateString(), $currentMonth));
        $presentDays = $monthAttendances->where('status', 'present')->count();
        $halfDays = $monthAttendances->where('status', 'half_day')->count();
        $absentDays = $monthAttendances->where('status', 'absent')->count();
        $leaveDays = $monthAttendances->where('status', 'leave')->count();

        // Salary calculations
        $agreedSalary = (float) $employee->agreed_salary;
        $accruedSalary = $agreedSalary;

        // If daily wage, evaluate distinct calendar dates to preserve salary rules (no arbitrary cuts or duplicate charges)
        if ($employee->salary_type === 'daily') {
            $datesGrouped = $monthAttendances->groupBy(fn ($a) => $a->attendance_date->toDateString());
            $effectiveDays = 0.0;
            foreach ($datesGrouped as $dateStr => $dayRecords) {
                if ($dayRecords->contains('status', 'present')) {
                    $effectiveDays += 1.0;
                } elseif ($dayRecords->contains('status', 'half_day')) {
                    $effectiveDays += 0.5;
                }
            }
            $accruedSalary = round($effectiveDays * $agreedSalary, 2);
        }

        $paidThisMonth = $employee->salaryPayments
            ->where('month_year', $currentMonth)
            ->sum('amount');

        $totalAdvancesTaken = $employee->advances->sum('amount');
        $outstandingAdvances = $employee->advances->sum('remaining_balance');

        // Held salary deposit (Security deposit / 3-5 days on hold)
        $heldDeposit = 0.00;
        if ((float) $employee->held_salary_amount > 0) {
            $heldDeposit = (float) $employee->held_salary_amount;
        } elseif ((int) $employee->held_salary_days > 0) {
            $dailyRate = $employee->salary_type === 'daily' 
                ? (float) $employee->agreed_salary 
                : round((float) $employee->agreed_salary / 30, 2);
            $heldDeposit = round($dailyRate * (int) $employee->held_salary_days, 2);
        }

        $remainingPayable = max(0, round($accruedSalary - $paidThisMonth - $heldDeposit, 2));

        return [
            'employee' => $employee,
            'current_month' => $currentMonth,
            'agreed_salary' => $agreedSalary,
            'accrued_salary' => $accruedSalary,
            'held_salary_days' => (int) ($employee->held_salary_days ?? 0),
            'held_salary_deposit' => $heldDeposit,
            'paid_this_month' => round($paidThisMonth, 2),
            'remaining_payable' => $remainingPayable,
            'outstanding_advances' => round($outstandingAdvances, 2),
            'total_advances_taken' => round($totalAdvancesTaken, 2),
            'attendance_summary' => [
                'present' => $presentDays,
                'half_day' => $halfDays,
                'absent' => $absentDays,
                'leave' => $leaveDays,
                'total_recorded' => $monthAttendances->count(),
            ],
            'attendances' => $employee->attendances,
            'salary_payments' => $employee->salaryPayments,
            'advances' => $employee->advances,
            'deductions' => $employee->deductions,
        ];
    }

    protected function saveImageFileOrBase64(mixed $fileOrData, string $folder): ?string
    {
        if (! $fileOrData) {
            return null;
        }

        if ($fileOrData instanceof UploadedFile) {
            return $fileOrData->store($folder, 'public');
        }

        if (is_string($fileOrData) && str_starts_with($fileOrData, 'data:image/')) {
            $parts = explode(',', $fileOrData);
            if (count($parts) === 2) {
                $decoded = base64_decode($parts[1]);
                $filename = $folder.'/'.Str::random(32).'.jpg';
                Storage::disk('public')->put($filename, $decoded);

                return $filename;
            }
        }

        return null;
    }
}
