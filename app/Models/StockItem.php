<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'category',
        'purchase_unit_id',
        'stock_unit_id',
        'recipe_unit_id',
        'chart_account_id',
        'cost_price',
        'current_stock',
        'min_reorder_level',
        'is_perishable',
        'track_batches',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'current_stock' => 'decimal:4',
        'min_reorder_level' => 'decimal:4',
        'is_perishable' => 'boolean',
        'track_batches' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id');
    }

    public function recipeUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'recipe_unit_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_account_id');
    }

    public function locationQuantities(): HasMany
    {
        return $this->hasMany(StockLocationQuantity::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }
}
