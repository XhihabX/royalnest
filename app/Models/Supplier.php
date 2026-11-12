<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'contact_person',
        'phone',
        'alternative_phone',
        'email',
        'address',
        'business_location',
        'category',
        'status',
        'preferred_payment_terms',
        'notes',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class)->orderBy('payment_date', 'desc');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(SupplierAdvance::class)->orderBy('advance_date', 'desc');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDirectory(Builder $query): Builder
    {
        return $query->where('status', '!=', 'archived');
    }
}
