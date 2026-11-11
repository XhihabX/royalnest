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
        'stock_item_id',
        'stock_location_id',
        'batch_no',
        'purchase_price',
        'initial_quantity',
        'remaining_quantity',
        'received_date',
        'expiry_date',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'initial_quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }
}
