<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\StockLedger;
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
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'selectedDate' => $today,
            'summary' => $summary,
            'lowStockProducts' => $lowStockProducts,
            'accounts' => $accounts,
            'recentMovements' => $recentMovements,
        ]);
    }
}

