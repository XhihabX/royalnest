<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'menu_category_id',
        'name',
        'code',
        'description',
        'selling_price',
        'vat_rate',
        'vat_mode',
        'kitchen_station',
        'is_available',
        'image_url',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }
}
