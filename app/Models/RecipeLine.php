<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_version_id',
        'stock_item_id',
        'sub_recipe_id',
        'quantity',
        'unit_id',
        'waste_percent',
        'yield_percent',
        'calculated_cost',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percent' => 'decimal:2',
        'yield_percent' => 'decimal:2',
        'calculated_cost' => 'decimal:4',
    ];

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'sub_recipe_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
