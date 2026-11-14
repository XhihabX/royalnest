<?php

namespace App\Http\Controllers;

use App\Models\DailySale;
use App\Services\CashReconciliationService;
use App\Services\DailySalesService;
use App\Services\FinancialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashReconciliationController extends Controller
{
    public function __construct(
        protected CashReconciliationService $cashReconciliationService,
        protected FinancialAccountService $financialAccountService,
        protected DailySalesService $dailySalesService
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
     * Import confirmed estimated cash sales into DailySale record and synchronize with financial accounts.
     */
    public function syncToSales(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'estimated_cash_sales' => 'required|numeric|min:0',
            'confirm_overwrite' => 'nullable|boolean',
        ]);

        $date = $validated['date'];
        $estCash = round((float) $validated['estimated_cash_sales'], 2);

        // Security / Accounting guard: Check reconciliation status
        $recon = \App\Models\DailyCashReconciliation::whereDate('reconciliation_date', $date)->first();
        if ($recon && $recon->status === 'confirmed' && ! auth()->user()?->hasRole(['manager', 'owner', 'admin'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date' => "Daily cash reconciliation for {$date} is locked and confirmed. Only a manager or owner can synchronize sales from a confirmed reconciliation.",
            ]);
        }

        $result = $this->dailySalesService->syncFromCashReconciliation(
            date: $date,
            estimatedCashSales: $estCash,
            userId: auth()->id() ?: 1,
            confirmOverwrite: (bool) ($validated['confirm_overwrite'] ?? false)
        );

        return redirect()->back()->with('success', "Estimated cash sales of ৳{$estCash} synchronized with Daily Sales and Cash Account.");
    }
}
