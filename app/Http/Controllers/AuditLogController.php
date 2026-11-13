<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Display a listing of system activity logs.
     */
    public function index(Request $request): Response
    {
        $startDate = $request->query('start_date', now()->subDays(7)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $module = $request->query('module', 'all');
        $action = $request->query('action', 'all');
        $userId = $request->query('user_id', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = AuditLog::query()
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        if ($module !== 'all' && $module !== '') {
            $query->where('module', $module);
        }

        if ($action !== 'all' && $action !== '') {
            $query->where('action', $action);
        }

        if ($userId !== 'all' && $userId !== '') {
            $query->where('user_id', $userId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('id')->paginate(30)->withQueryString();

        $users = User::select('id', 'name')->orderBy('name')->get();

        $modules = [
            'all' => 'All Modules',
            'auth' => 'Authentication',
            'purchases' => 'Purchases & Inventory',
            'expenses' => 'Operating Expenses',
            'suppliers' => 'Suppliers & Payments',
            'staff' => 'Staff & Payroll',
            'sales' => 'Daily Sales',
            'accounts' => 'Financial Accounts',
            'rbac' => 'Users & Permissions',
            'recycle_bin' => 'Recycle Bin',
        ];

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'module' => $module,
                'action' => $action,
                'user_id' => $userId,
                'search' => $search,
            ],
            'users' => $users,
            'modules' => $modules,
        ]);
    }

    /**
     * Export activity logs to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $startDate = $request->query('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $module = $request->query('module', 'all');

        $query = AuditLog::query()
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        if ($module !== 'all' && $module !== '') {
            $query->where('module', $module);
        }

        $logs = $query->latest('id')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"audit_logs_{$startDate}_to_{$endDate}.csv\"",
        ];

        return response()->stream(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date/Time', 'Actor', 'Module', 'Action', 'Reference', 'Description', 'Reason', 'Status', 'IP Address']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at->toDateTimeString(),
                    $log->user_name,
                    $log->module,
                    $log->action,
                    $log->transaction_reference ?? 'N/A',
                    $log->description,
                    $log->reason ?? '',
                    $log->status,
                    $log->ip_address,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

