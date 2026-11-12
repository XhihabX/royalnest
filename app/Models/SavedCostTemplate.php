<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedCostTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'transaction_type',
        'category_id',
        'product_id',
        'supplier_id',
        'employee_id',
        'expense_category',
        'default_unit',
        'last_unit_price',
        'default_payment_account_id',
        'is_favorite',
        'use_count',
        'last_used_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'last_unit_price' => 'decimal:4',
        'is_favorite' => 'boolean',
        'is_active' => 'boolean',
        'use_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function defaultPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'default_payment_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
