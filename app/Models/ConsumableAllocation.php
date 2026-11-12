<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_id',
        'start_date',
        'end_date',
        'total_cost',
        'duration_days',
        'daily_allocated_cost',
        'status',
        'confirmed_actual_cost',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'total_cost' => 'decimal:2',
        'daily_allocated_cost' => 'decimal:2',
        'confirmed_actual_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
