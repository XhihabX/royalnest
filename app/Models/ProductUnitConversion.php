<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnitConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_unit',
        'multiplier_to_base',
    ];

    protected $casts = [
        'multiplier_to_base' => 'decimal:6',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
