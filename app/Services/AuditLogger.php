<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record an immutable audit log entry.
     */
    public static function log(
        string $action,
        string $description,
        ?Model $entity = null,
        ?string $reason = null,
        ?array $beforePayload = null,
        ?array $afterPayload = null,
        ?int $branchId = null
    ): AuditLog {
        $user = Auth::user();
        $resolvedBranchId = $branchId ?? ($user ? $user->branch_id : null);
        $userName = $user ? $user->name : 'System';

        return AuditLog::create([
            'branch_id' => $resolvedBranchId,
            'user_id' => $user ? $user->id : null,
            'user_name' => $userName,
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity ? $entity->getKey() : null,
            'description' => $description,
            'reason' => $reason,
            'before_payload' => $beforePayload,
            'after_payload' => $afterPayload,
            'ip_address' => request()?->ip(),
            'user_agent' => substr(request()?->header('User-Agent') ?? '', 0, 255),
            'created_at' => now(),
        ]);
    }
}
