<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Cache of logged event hashes for the current request to prevent duplicates.
     *
     * @var array<string, bool>
     */
    protected static array $loggedHashes = [];

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
        ?int $branchId = null,
        string $module = 'general',
        ?string $transactionReference = null,
        string $status = 'success',
        ?string $errorMessage = null
    ): ?AuditLog {
        $user = Auth::user();
        $resolvedBranchId = $branchId ?? ($user ? $user->branch_id : 1);
        $userName = $user ? $user->name : 'System';

        $entityType = $entity ? class_basename($entity) : null;
        $entityId = $entity ? $entity->getKey() : null;

        // Sanitization of sensitive attributes
        $cleanBefore = self::sanitizePayload($beforePayload);
        $cleanAfter = self::sanitizePayload($afterPayload);

        // Idempotency deduplication key
        $eventHash = md5($action . $module . $entityType . $entityId . $status . ($transactionReference ?? ''));
        if (isset(self::$loggedHashes[$eventHash])) {
            return null; // Suppress duplicate event in same request cycle
        }
        self::$loggedHashes[$eventHash] = true;

        $eventUuid = (string) Str::uuid();

        return AuditLog::create([
            'event_uuid' => $eventUuid,
            'branch_id' => $resolvedBranchId,
            'user_id' => $user ? $user->id : null,
            'user_name' => $userName,
            'module' => $module,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'transaction_reference' => $transactionReference,
            'description' => $description,
            'reason' => $reason,
            'before_payload' => $cleanBefore,
            'after_payload' => $cleanAfter,
            'status' => $status,
            'error_message' => $errorMessage,
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => substr(request()?->header('User-Agent') ?? 'CLI', 0, 255),
            'created_at' => now(),
        ]);
    }

    /**
     * Log a failed operation safely, persisting even if a database transaction rolled back.
     */
    public static function logFailure(
        string $action,
        string $description,
        string $errorMessage,
        string $module = 'general',
        ?string $transactionReference = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): void {
        try {
            $user = Auth::user();
            $userName = $user ? $user->name : 'System';
            $branchId = $user ? $user->branch_id : 1;

            DB::table('audit_logs')->insert([
                'event_uuid' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'user_id' => $user ? $user->id : null,
                'user_name' => $userName,
                'module' => $module,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'transaction_reference' => $transactionReference,
                'description' => $description,
                'reason' => 'Failed transaction',
                'before_payload' => null,
                'after_payload' => null,
                'status' => 'failed',
                'error_message' => substr($errorMessage, 0, 1000),
                'ip_address' => request()?->ip() ?? '127.0.0.1',
                'user_agent' => substr(request()?->header('User-Agent') ?? 'CLI', 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail silently to never break critical flow if logging fails
        }
    }

    /**
     * Remove sensitive fields from payload.
     */
    protected static function sanitizePayload(?array $payload): ?array
    {
        if (!$payload) {
            return null;
        }

        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'remember_token',
            'security_pin',
            'token',
            'secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[FILTERED]';
            }
        }

        return $payload;
    }
}
