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
        'status',
        'revised_by_id',
        'revised_at',
        'payload_hash',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'consumption_date' => 'date:Y-m-d',
        'total_cost' => 'decimal:2',
        'revised_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsumptionItem::class);
    }
}
