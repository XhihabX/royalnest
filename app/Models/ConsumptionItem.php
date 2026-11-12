<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumption_id',
        'product_id',
        'stock_batch_id',
        'quantity_used',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(Consumption::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
