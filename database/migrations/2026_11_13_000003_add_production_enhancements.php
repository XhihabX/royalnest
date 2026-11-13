<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users: Add is_super_admin identity flag
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('primary_role')->index();
        });

        // Safe Super Admin verification algorithm
        $adminCandidates = DB::table('users')
            ->where('primary_role', 'owner')
            ->where('is_active', true)
            ->get();

        if ($adminCandidates->count() === 1) {
            $primaryAdmin = $adminCandidates->first();
            if ($primaryAdmin->email === 'admin@royalnest.com' && $primaryAdmin->id === 1) {
                DB::table('users')->where('id', $primaryAdmin->id)->update([
                    'is_super_admin' => true,
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Categories: Add soft deletes support
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes()->after('is_active');
        });

        // 3. Audit Logs: Add UUID, module, reference, status, and error message
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event_uuid', 64)->nullable()->after('id')->index();
            $table->string('module', 50)->default('general')->after('user_name')->index();
            $table->string('transaction_reference', 100)->nullable()->after('entity_id')->index();
            $table->string('status', 20)->default('success')->after('after_payload')->index();
            $table->text('error_message')->nullable()->after('status');
        });

        // 4. Financial Tables: Add Reversal metadata & idempotency tracking
        $financialTables = [
            'purchases',
            'supplier_payments',
            'employee_advances',
            'salary_payments',
            'operating_expenses',
            'daily_sales',
        ];

        foreach ($financialTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('reversal_status', 30)->default('active')->nullable()->index();
                    $table->string('reversal_type', 30)->nullable();
                    $table->timestamp('reversed_at')->nullable();
                    $table->unsignedBigInteger('reversed_by')->nullable()->index();
                    $table->text('reversal_reason')->nullable();
                    $table->unsignedBigInteger('compensating_transaction_id')->nullable()->index();
                });
            }
        }

        // 5. RBAC: Ensure visitor role and seed granular permissions
        $visitorRole = DB::table('roles')->where('name', 'visitor')->first();
        if (!$visitorRole) {
            $visitorRoleId = DB::table('roles')->insertGetId([
                'name' => 'visitor',
                'display_name' => 'Visitor (Read-Only)',
                'description' => 'Strict read-only demonstration and auditor account with zero write permissions',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $newPermissions = [
            ['name' => 'view_audit_logs', 'display_name' => 'View Activity Audit Logs', 'module' => 'system'],
            ['name' => 'manage_recycle_bin', 'display_name' => 'Manage Recycle Bin Records', 'module' => 'system'],
            ['name' => 'manage_users', 'display_name' => 'Manage Users and Roles', 'module' => 'system'],
            ['name' => 'reverse_transactions', 'display_name' => 'Reverse or Void Financial Transactions', 'module' => 'financial'],
            ['name' => 'view_sensitive_staff', 'display_name' => 'View Sensitive Staff Data (NID, Salary)', 'module' => 'staff'],
            ['name' => 'view_sensitive_financials', 'display_name' => 'View Sensitive Bank and Cash Float Details', 'module' => 'financial'],
        ];

        foreach ($newPermissions as $perm) {
            $exists = DB::table('permissions')->where('name', $perm['name'])->first();
            if (!$exists) {
                $permId = DB::table('permissions')->insertGetId(array_merge($perm, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                // Attach to Owner (Super Admin) and Manager where appropriate
                $ownerRole = DB::table('roles')->where('name', 'owner')->first();
                if ($ownerRole) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $ownerRole->id,
                        'permission_id' => $permId,
                    ]);
                }

                $managerRole = DB::table('roles')->where('name', 'manager')->first();
                if ($managerRole && in_array($perm['name'], ['view_audit_logs', 'manage_recycle_bin', 'reverse_transactions'])) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $managerRole->id,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $financialTables = [
            'purchases',
            'supplier_payments',
            'employee_advances',
            'salary_payments',
            'operating_expenses',
            'daily_sales',
        ];

        foreach ($financialTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn([
                        'reversal_status',
                        'reversal_type',
                        'reversed_at',
                        'reversed_by',
                        'reversal_reason',
                        'compensating_transaction_id',
                    ]);
                });
            }
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['event_uuid', 'module', 'transaction_reference', 'status', 'error_message']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};

