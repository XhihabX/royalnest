<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'advance_date',
        'amount',
        'payment_account_id',
        'payment_method',
        'voucher_no',
        'reason',
        'remaining_balance',
        'status',
        'created_by',
        'reversal_status',
        'reversal_type',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'compensating_transaction_id',
    ];

    protected $casts = [
        'advance_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'payment_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SalaryDeduction::class);
    }
}
