<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAttendance;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalaryPayment;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\ProfitLossService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected ProfitLossService $profitLossService
    ) {}

    public function index(Request $request): Response
    {
        $today = $request->input('date', now()->toDateString());
        $summary = $this->profitLossService->getDailySummary($today);

        // Low stock products
        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_reorder_level')
            ->with('category')
            ->get();

        // Accounts
        $accounts = FinancialAccount::where('is_active', true)->get();

        // Recent stock movements
        $recentMovements = StockLedger::with(['product', 'user'])
            ->latest('id')
            ->limit(8)
            ->get();

        // Supplier Cross-Module Stats
        $activeSuppliersCount = Supplier::where('status', 'active')->count();
        $totalSupplierPayable = (float) Supplier::sum('current_balance');
        $recentPurchases = Purchase::with(['supplier', 'creator'])->latest('purchase_date')->limit(5)->get();
        $recentSupplierPayments = SupplierPayment::with(['supplier', 'account'])->latest('payment_date')->limit(5)->get();

        // Staff & Payroll Cross-Module Stats
        $activeStaffCount = Employee::where('status', 'active')->count();
        $todayAttendances = EmployeeAttendance::where('attendance_date', $today)->get();
        $attendanceSummary = [
            'present' => $todayAttendances->where('status', 'present')->count(),
            'half_day' => $todayAttendances->where('status', 'half_day')->count(),
            'absent' => $todayAttendances->where('status', 'absent')->count(),
            'leave' => $todayAttendances->where('status', 'leave')->count(),
            'unmarked' => max(0, $activeStaffCount - $todayAttendances->count()),
            'total_active' => $activeStaffCount,
        ];

        $totalStaffAdvances = (float) EmployeeAdvance::sum('remaining_balance');
        $recentSalaryPayments = SalaryPayment::with(['employee', 'account'])->latest('payment_date')->limit(5)->get();

        return Inertia::render('Dashboard', [
            'selectedDate' => $today,
            'summary' => $summary,
            'lowStockProducts' => $lowStockProducts,
            'accounts' => $accounts,
            'recentMovements' => $recentMovements,
            'supplierStats' => [
                'active_count' => $activeSuppliersCount,
                'total_payable' => round($totalSupplierPayable, 2),
                'recent_purchases' => $recentPurchases,
                'recent_payments' => $recentSupplierPayments,
            ],
            'staffStats' => [
                'active_count' => $activeStaffCount,
                'attendance' => $attendanceSummary,
                'outstanding_advances' => round($totalStaffAdvances, 2),
                'recent_payments' => $recentSalaryPayments,
            ],
        ]);
    }
}
