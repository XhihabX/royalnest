<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_no',
        'branch_id',
        'business_date',
        'transaction_date',
        'posting_date',
        'source_module',
        'source_id',
        'reference',
        'description',
        'total_debit',
        'total_credit',
        'is_posted',
        'is_reversed',
        'reversed_by_journal_id',
        'created_by',
    ];

    protected $casts = [
        'business_date' => 'date',
        'transaction_date' => 'date',
        'posting_date' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_posted' => 'boolean',
        'is_reversed' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reversalJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_journal_id');
    }
}

