<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCashReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_date',
        'yesterday_closing_cash',
        'opening_cash',
        'opening_adjustment',
        'today_cash_sales',
        'other_cash_receipts',
        'today_cash_payments',
        'counted_closing_cash',
        'estimated_cash_sales',
        'recorded_non_cash_sales',
        'estimated_total_sales',
        'reconciliation_difference',
        'status',
        'notes',
        'created_by',
        'confirmed_by',
    ];

    protected $casts = [
        'reconciliation_date' => 'date:Y-m-d',
        'yesterday_closing_cash' => 'decimal:2',
        'opening_cash' => 'decimal:2',
        'opening_adjustment' => 'decimal:2',
        'today_cash_sales' => 'decimal:2',
        'other_cash_receipts' => 'decimal:2',
        'today_cash_payments' => 'decimal:2',
        'counted_closing_cash' => 'decimal:2',
        'estimated_cash_sales' => 'decimal:2',
        'recorded_non_cash_sales' => 'decimal:2',
        'estimated_total_sales' => 'decimal:2',
        'reconciliation_difference' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
