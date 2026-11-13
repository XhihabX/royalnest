<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_uuid',
        'branch_id',
        'user_id',
        'user_name',
        'module',
        'action',
        'entity_type',
        'entity_id',
        'transaction_reference',
        'description',
        'reason',
        'before_payload',
        'after_payload',
        'status',
        'error_message',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before_payload' => 'array',
        'after_payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Audit logs are immutable and cannot be modified.'
            );
        });

        static::deleting(function () {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Audit logs are immutable and cannot be deleted.'
            );
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
