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
        'reversal_status',
        'reversal_type',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'compensating_transaction_id',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_purchase_and_consume' => 'boolean',
        'reversed_at' => 'datetime',
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

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'purchase_id');
    }

    public function getDueAmountAttribute(): float
    {
        $allocated = (float) $this->paymentAllocations()->sum('allocated_amount');

        return max(0.0, round((float) $this->total_amount - (float) $this->paid_amount - $allocated, 2));
    }

    public function getPaymentStatusAttribute(): string
    {
        $allocated = (float) $this->paymentAllocations()->sum('allocated_amount');
        $effectivePaid = (float) $this->paid_amount + $allocated;
        if ($effectivePaid >= (float) $this->total_amount) {
            return 'paid';
        }
        if ($effectivePaid > 0) {
            return 'partial';
        }

        return 'due';
    }
}
