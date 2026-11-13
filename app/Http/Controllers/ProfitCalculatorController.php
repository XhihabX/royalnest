<?php

namespace App\Http\Controllers;

use App\Models\Consumption;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\StockBatch;
use App\Services\ConsumptionService;
use App\Services\ProfitLossService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfitCalculatorController extends Controller
{
    public function __construct(
        protected ConsumptionService $consumptionService,
        protected ProfitLossService $profitLossService
    ) {}

    /**
     * Display the Interactive Daily Profit & Loss Calculator.
     */
    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());

        // 1. Fetch products with active FIFO batch pricing
        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                // Determine current FIFO batch price: oldest non-exhausted batch
                $oldestBatch = StockBatch::where('product_id', $product->id)
                    ->where('is_exhausted', false)
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('received_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                $fifoCost = $oldestBatch ? (float) $oldestBatch->unit_cost : (float) $product->cost_price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'base_unit' => $product->base_unit,
                    'current_stock' => (float) $product->current_stock,
                    'fifo_unit_cost' => round($fifoCost, 2),
                    'category_name' => $product->category?->name ?? 'Raw Materials',
                    'available_batch_no' => $oldestBatch?->batch_no ?? 'Default',
                ];
            });

        // 2. Fetch recorded sales for this date
        $sale = DailySale::whereDate('sales_date', $date)->first();
        $salesData = [
            'total_sales' => $sale ? (float) $sale->total_sales : 0.00,
            'cash_sales' => $sale ? (float) $sale->cash_sales : 0.00,
            'bkash_sales' => $sale ? (float) $sale->bkash_sales : 0.00,
            'nagad_sales' => $sale ? (float) $sale->nagad_sales : 0.00,
            'card_bank_sales' => $sale ? (float) $sale->card_bank_sales : 0.00,
            'other_sales' => $sale ? (float) $sale->other_sales : 0.00,
            'is_recorded' => (bool) $sale,
        ];

        // 3. Fetch already recorded kitchen usage/consumptions for this date
        $recordedConsumptions = Consumption::whereDate('consumption_date', $date)
            ->with(['items.product', 'creator'])
            ->latest('id')
            ->get();

        $alreadyConsumedTotal = (float) $recordedConsumptions->sum('total_cost');

        // 4. Operating Expenses for this date
        $operatingExpenses = OperatingExpense::whereDate('expense_date', $date)
            ->with('financialAccount')
            ->get();
        $operatingExpensesTotal = (float) $operatingExpenses->sum('amount');

        // 5. Daily Staff Salary Cost Accrual from ProfitLossService
        $dailySummary = $this->profitLossService->getDailySummary($date);
        $staffSalaryCost = (float) ($dailySummary['operating_costs']['salary_cost'] ?? 0.00);
        $consumablesAllocated = (float) ($dailySummary['cost_of_materials']['consumables_allocated'] ?? 0.00);
        $wastageCost = (float) ($dailySummary['operating_costs']['wastage_cost'] ?? 0.00);

        // 6. Accounts
        $accounts = FinancialAccount::where('is_active', true)->get();

        return Inertia::render('Calculator/Daily', [
            'date' => $date,
            'products' => $products,
            'salesData' => $salesData,
            'recordedConsumptions' => $recordedConsumptions,
            'alreadyConsumedTotal' => round($alreadyConsumedTotal, 2),
            'operatingExpenses' => $operatingExpenses,
            'operatingExpensesTotal' => round($operatingExpensesTotal, 2),
            'staffSalaryCost' => round($staffSalaryCost, 2),
            'consumablesAllocated' => round($consumablesAllocated, 2),
            'wastageCost' => round($wastageCost, 2),
            'accounts' => $accounts,
        ]);
    }

    /**
     * Save daily raw material usage and synchronize sales and profits.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'sales' => 'nullable|array',
            'sales.total_sales' => 'nullable|numeric|min:0',
            'sales.cash_sales' => 'nullable|numeric|min:0',
            'sales.bkash_sales' => 'nullable|numeric|min:0',
            'sales.nagad_sales' => 'nullable|numeric|min:0',
            'sales.card_bank_sales' => 'nullable|numeric|min:0',
            'sales.other_sales' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_used' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string|max:500',
        ]);

        $userId = auth()->id() ?: 1;
        $date = $validated['date'];

        DB::transaction(function () use ($date, $validated, $userId) {
            // 1. Record Ingredient Consumption via FIFO if any items provided
            if (! empty($validated['items'])) {
                $this->consumptionService->recordConsumption(
                    date: $date,
                    items: $validated['items'],
                    userId: $userId,
                    notes: $validated['notes'] ?? 'Daily P&L Calculator Usage'
                );
            }

            // 2. Update or Create Daily Sale if sales provided
            if (! empty($validated['sales'])) {
                $salesData = $validated['sales'];
                $totalSales = (float) ($salesData['total_sales'] ?? 0);
                $cashSales = (float) ($salesData['cash_sales'] ?? 0);
                $bkashSales = (float) ($salesData['bkash_sales'] ?? 0);
                $nagadSales = (float) ($salesData['nagad_sales'] ?? 0);
                $cardSales = (float) ($salesData['card_bank_sales'] ?? 0);
                $otherSales = (float) ($salesData['other_sales'] ?? 0);

                // If breakdown sums differ from total sales, auto-reconcile or preserve breakdown
                $breakdownSum = round($cashSales + $bkashSales + $nagadSales + $cardSales + $otherSales, 2);
                if ($totalSales <= 0 && $breakdownSum > 0) {
                    $totalSales = $breakdownSum;
                }

                DailySale::updateOrCreate(
                    ['sales_date' => $date],
                    [
                        'total_sales' => $totalSales,
                        'cash_sales' => $cashSales,
                        'bkash_sales' => $bkashSales,
                        'nagad_sales' => $nagadSales,
                        'card_bank_sales' => $cardSales,
                        'other_sales' => $otherSales,
                        'discrepancy_amount' => 0.00,
                        'notes' => 'Saved via Daily P&L Calculator',
                        'created_by' => $userId,
                    ]
                );
            }
        });

        return redirect()->back()->with('success', "Daily raw material usage & sales for {$date} saved and synchronized successfully.");
    }
}

