<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'invoice_no',
        'purchase_date',
        'total_amount',
        'paid_amount',
        'status',
        'is_purchase_and_consume',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_purchase_and_consume' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function consumableAllocations(): HasMany
    {
        return $this->hasMany(ConsumableAllocation::class);
    }
}
