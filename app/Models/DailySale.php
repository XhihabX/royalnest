<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_date',
        'total_sales',
        'cash_sales',
        'bkash_sales',
        'nagad_sales',
        'card_bank_sales',
        'other_sales',
        'discrepancy_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sales_date' => 'date:Y-m-d',
        'total_sales' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'bkash_sales' => 'decimal:2',
        'nagad_sales' => 'decimal:2',
        'card_bank_sales' => 'decimal:2',
        'other_sales' => 'decimal:2',
        'discrepancy_amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
