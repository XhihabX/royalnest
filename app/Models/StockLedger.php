<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedger extends Model
{
    use HasFactory;

    protected $table = 'stock_ledger';
    public $timestamps = false; // migration has created_at timestamp default current

    protected $fillable = [
        'product_id',
        'stock_batch_id',
        'movement_type',
        'transaction_date',
        'quantity_in',
        'quantity_out',
        'balance_after',
        'unit_cost',
        'total_value',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity_in' => 'decimal:4',
        'quantity_out' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

