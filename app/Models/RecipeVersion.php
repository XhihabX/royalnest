<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'version_no',
        'is_active',
        'effective_date',
        'calculated_cost_price',
        'food_cost_percentage',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'calculated_cost_price' => 'decimal:4',
        'food_cost_percentage' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }
}
