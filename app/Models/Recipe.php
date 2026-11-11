<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'menu_item_id',
        'variant_id',
        'name',
        'recipe_type',
        'batch_yield_quantity',
        'yield_unit_id',
        'notes',
    ];

    protected $casts = [
        'batch_yield_quantity' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'variant_id');
    }

    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'yield_unit_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(RecipeVersion::class)->where('is_active', true)->latestOfMany();
    }
}
