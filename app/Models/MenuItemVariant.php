<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenuItemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'name',
        'price_modifier',
        'selling_price',
        'is_active',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'variant_id');
    }
}
