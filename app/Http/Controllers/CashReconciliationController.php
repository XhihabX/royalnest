<?php

namespace App\Http\Controllers;

use App\Models\DailySale;
use App\Services\CashReconciliationService;
use App\Services\FinancialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashReconciliationController extends Controller
{
    public function __construct(
        protected CashReconciliationService $cashReconciliationService,
        protected FinancialAccountService $financialAccountService
    ) {}

    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());
        $data = $this->cashReconciliationService->getReconciliationData($date);

        return Inertia::render('Cash/Reconciliation', [
            'data' => $data,
            'selectedDate' => $date,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reconciliation_date' => 'required|date',
            'opening_cash' => 'required|numeric|min:0',
            'counted_closing_cash' => 'nullable|numeric|min:0',
            'yesterday_closing_cash' => 'nullable|numeric|min:0',
            'other_cash_receipts' => 'nullable|numeric|min:0',
            'today_cash_payments' => 'nullable|numeric|min:0',
            'recorded_non_cash_sales' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:draft,confirmed',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->cashReconciliationService->saveReconciliation($validated, auth()->id() ?: 1);

        return redirect()->back()->with('success', 'Daily cash reconciliation saved successfully.');
    }

    /**
     * Import confirmed estimated cash sales into DailySale record.
     */
    public function syncToSales(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'estimated_cash_sales' => 'required|numeric|min:0',
        ]);

        $date = $validated['date'];
        $estCash = round((float) $validated['estimated_cash_sales'], 2);

        $sale = DailySale::whereDate('sales_date', $date)->first();

        if ($sale) {
            $otherSales = (float) ($sale->bkash_sales + $sale->nagad_sales + $sale->card_bank_sales + $sale->other_sales);
            $newTotal = round($estCash + $otherSales, 2);

            $sale->cash_sales = $estCash;
            $sale->total_sales = $newTotal;
            $sale->discrepancy_amount = 0.00;
            $sale->notes = ($sale->notes ? $sale->notes.' | ' : '').'Cash sales synchronized from Cash Reconciliation.';
            $sale->save();
        } else {
            DailySale::create([
                'sales_date' => $date,
                'total_sales' => $estCash,
                'cash_sales' => $estCash,
                'bkash_sales' => 0.00,
                'nagad_sales' => 0.00,
                'card_bank_sales' => 0.00,
                'other_sales' => 0.00,
                'discrepancy_amount' => 0.00,
                'notes' => 'Generated from Daily Cash Reconciliation.',
                'created_by' => auth()->id() ?: 1,
            ]);
        }

        return redirect()->back()->with('success', "Estimated cash sales of ৳{$estCash} synchronized with Daily Sales.");
    }
}
