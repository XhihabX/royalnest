<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_item_id',
        'batch_no',
        'received_date',
        'initial_quantity',
        'remaining_quantity',
        'unit_cost',
        'is_exhausted',
    ];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
        'initial_quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'is_exhausted' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }

    public function consumptionItems(): HasMany
    {
        return $this->hasMany(ConsumptionItem::class);
    }
}
