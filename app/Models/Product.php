<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'product_type',
        'base_unit',
        'purchase_unit',
        'current_stock',
        'cost_price',
        'min_reorder_level',
        'is_consumable',
        'default_allocation_days',
        'supplier_id',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'min_reorder_level' => 'decimal:4',
        'is_consumable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class);
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function consumptionItems(): HasMany
    {
        return $this->hasMany(ConsumptionItem::class);
    }

    public function wastages(): HasMany
    {
        return $this->hasMany(Wastage::class);
    }

    public function consumableAllocations(): HasMany
    {
        return $this->hasMany(ConsumableAllocation::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function activeBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class)
            ->where('is_exhausted', false)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date', 'asc')
            ->orderBy('id', 'asc');
    }
}

