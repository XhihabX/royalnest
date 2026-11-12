<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'advance_date',
        'amount',
        'payment_account_id',
        'payment_method',
        'voucher_no',
        'reason',
        'remaining_balance',
        'created_by',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'payment_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
