<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumption_date',
        'total_cost',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'consumption_date' => 'date:Y-m-d',
        'total_cost' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsumptionItem::class);
    }
}
