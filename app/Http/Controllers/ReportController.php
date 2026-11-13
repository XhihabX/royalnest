<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAdvance;
use App\Models\OperatingExpense;
use App\Models\Purchase;
use App\Models\SalaryPayment;
use App\Models\SupplierPayment;
use App\Services\ProfitLossService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ProfitLossService $profitLossService
    ) {}

    public function profitLoss(Request $request): Response
    {
        $mode = $request->input('mode', 'daily'); // 'daily' or 'range'
        $date = $request->input('date', now()->toDateString());
        $startDate = $request->input('start_date', Carbon::parse($date)->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::parse($date)->endOfMonth()->toDateString());

        if ($mode === 'range') {
            $data = $this->profitLossService->getRangeSummary($startDate, $endDate);
        } else {
            $data = $this->profitLossService->getDailySummary($date);
        }

        return Inertia::render('Reports/ProfitLoss', [
            'mode' => $mode,
            'selectedDate' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $data,
        ]);
    }

    /**
     * Daily Purchases, Expenses & Cash Outflow Transactions Report.
     * Tab 1: Daily Purchases (Item, Qty, Unit, Unit Price, Line Total, Category Badge, Supplier).
     * Tab 2: Daily Expenses & Outflows (Strict separation: Operating Expenses vs Liability / Due Settlements vs Advances).
     * Tab 3: Combined Filterable Stream with 1-tap category pills.
     */
    public function transactions(Request $request): Response
    {
        $mode = $request->input('mode', 'daily');
        $date = $request->input('date', now()->toDateString());
        $startDate = $request->input('start_date', $mode === 'range' ? Carbon::parse($date)->startOfMonth()->toDateString() : $date);
        $endDate = $request->input('end_date', $mode === 'range' ? Carbon::parse($date)->endOfMonth()->toDateString() : $date);
        $activeTab = $request->input('tab', 'purchases');
        $categoryFilter = $request->input('category', 'all');

        // Purchases Query
        $purchases = Purchase::whereBetween('purchase_date', [$startDate, $endDate])
            ->with(['supplier', 'items.product.category', 'creator', 'paymentAllocations'])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $purchaseLines = [];
        foreach ($purchases as $p) {
            foreach ($p->items as $item) {
                $catType = $item->product?->product_type ?? $item->product?->category?->type ?? 'raw_material';
                $catName = $item->product?->category?->name ?? 'Raw Materials';
                $purchaseLines[] = [
                    'purchase_id' => $p->id,
                    'invoice_no' => $p->invoice_no,
                    'date' => $p->purchase_date->toDateString(),
                    'supplier_name' => $p->supplier?->name ?? 'Direct Purchase',
                    'item_name' => $item->product?->name ?? 'Custom Item',
                    'quantity' => (float) ($item->purchase_quantity ?? $item->quantity ?? 0),
                    'unit' => $item->purchase_unit ?? $item->unit ?? 'pcs',
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) ($item->subtotal ?? $item->total_price ?? 0),
                    'bill_total' => (float) $p->total_amount,
                    'paid_amount' => (float) $p->paid_amount,
                    'due_amount' => (float) $p->due_amount,
                    'category_type' => $catType,
                    'category_name' => $catName,
                    'payment_status' => $p->payment_status,
                    'reversal_status' => $p->reversal_status ?? 'active',
                ];
            }
        }

        // Operating Expenses (Strict P&L expense)
        $expenses = OperatingExpense::whereBetween('expense_date', [$startDate, $endDate])
            ->with(['financialAccount', 'creator'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => 'operating_expense',
                'type_label' => 'Operating Expense',
                'is_pnl_expense' => true,
                'date' => $e->expense_date->toDateString(),
                'name' => $e->payee ?: ucfirst($e->category).' Expense',
                'category' => $e->category,
                'amount' => (float) $e->amount,
                'account_name' => $e->financialAccount ? $e->financialAccount->name : 'Unlinked / Unknown',
                'notes' => $e->notes,
                'creator' => $e->creator?->name,
                'reversal_status' => $e->reversal_status ?? 'active',
                'account_type' => $e->financialAccount ? $e->financialAccount->type : 'unknown',
            ]);

        // Supplier Payments (Balance Sheet / Due Settlement - NOT P&L expense)
        $supplierPayments = SupplierPayment::whereBetween('payment_date', [$startDate, $endDate])
            ->with(['supplier', 'account', 'creator', 'allocations.purchase'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($sp) => [
                'id' => $sp->id,
                'type' => 'supplier_payment',
                'type_label' => 'Supplier Due Settlement',
                'is_pnl_expense' => false,
                'date' => $sp->payment_date->toDateString(),
                'name' => "Payment to {$sp->supplier?->name}",
                'category' => 'supplier_due_settlement',
                'amount' => (float) $sp->amount,
                'account_name' => $sp->account ? $sp->account->name : 'Unlinked / Unknown',
                'notes' => $sp->notes,
                'allocations' => $sp->allocations->map(fn ($a) => [
                    'purchase_invoice' => $a->purchase?->invoice_no,
                    'allocated_amount' => (float) $a->allocated_amount,
                ]),
                'creator' => $sp->creator?->name,
                'reversal_status' => $sp->reversal_status ?? 'active',
                'account_type' => $sp->account ? $sp->account->type : 'unknown',
            ]);

        // Salary Payments (Payroll Liability Settlement - NOT double counted in P&L)
        $salaryPayments = SalaryPayment::whereBetween('payment_date', [$startDate, $endDate])
            ->with(['employee', 'account', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($sal) => [
                'id' => $sal->id,
                'type' => 'salary_payment',
                'type_label' => 'Staff Salary Payout',
                'is_pnl_expense' => false,
                'date' => $sal->payment_date->toDateString(),
                'name' => "Salary Payout - {$sal->employee?->name} ({$sal->employee?->role})",
                'category' => 'payroll_disbursal',
                'amount' => (float) $sal->amount,
                'account_name' => $sal->account ? $sal->account->name : 'Unlinked / Unknown',
                'notes' => "Month: {$sal->month_year}".($sal->notes ? " | {$sal->notes}" : ''),
                'creator' => $sal->creator?->name,
                'reversal_status' => $sal->reversal_status ?? 'active',
                'account_type' => $sal->account ? $sal->account->type : 'unknown',
            ]);

        // Staff Advances (Asset / Receivable - NOT P&L expense)
        $advances = EmployeeAdvance::whereBetween('advance_date', [$startDate, $endDate])
            ->with(['employee', 'account', 'creator'])
            ->orderBy('advance_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($adv) => [
                'id' => $adv->id,
                'type' => 'advance',
                'type_label' => 'Staff Advance',
                'is_pnl_expense' => false,
                'date' => $adv->advance_date->toDateString(),
                'name' => "Advance to {$adv->employee?->name} ({$adv->voucher_no})",
                'category' => 'employee_advance',
                'amount' => (float) $adv->amount,
                'account_name' => $adv->account ? $adv->account->name : 'Unlinked / Unknown',
                'notes' => $adv->reason,
                'creator' => $adv->creator?->name,
                'reversal_status' => $adv->reversal_status ?? 'active',
                'account_type' => $adv->account ? $adv->account->type : 'unknown',
            ]);

        // Combine all outflows for Tab 2 & 3
        $allOutflows = collect([...$expenses, ...$supplierPayments, ...$salaryPayments, ...$advances])
            ->sortByDesc('date')
            ->values()
            ->all();

        // Calculate Totals (strictly excluding voided records so active totals reflect real money movements)
        $activePurchaseLines = collect($purchaseLines)->filter(fn ($p) => ($p['reversal_status'] ?? 'active') !== 'voided');
        $activeExpenses = collect($expenses)->filter(fn ($e) => ($e['reversal_status'] ?? 'active') !== 'voided');
        $activeSupplierPayments = collect($supplierPayments)->filter(fn ($sp) => ($sp['reversal_status'] ?? 'active') !== 'voided');
        $activeSalaryPayments = collect($salaryPayments)->filter(fn ($sal) => ($sal['reversal_status'] ?? 'active') !== 'voided');
        $activeAdvances = collect($advances)->filter(fn ($adv) => ($adv['reversal_status'] ?? 'active') !== 'voided');

        $totalPurchases = round($activePurchaseLines->sum('line_total'), 2);
        $totalRawMaterials = round($activePurchaseLines->where('category_type', 'raw_material')->sum('line_total'), 2);
        $totalPackaging = round($activePurchaseLines->where('category_type', 'packaging')->sum('line_total'), 2);
        $totalConsumables = round($activePurchaseLines->whereNotIn('category_type', ['raw_material', 'packaging'])->sum('line_total'), 2);

        $totalOperatingExpenses = round($activeExpenses->sum('amount'), 2);
        $totalSupplierPayments = round($activeSupplierPayments->sum('amount'), 2);
        $totalSalaryPayments = round($activeSalaryPayments->sum('amount'), 2);
        $totalAdvancesIssued = round($activeAdvances->sum('amount'), 2);
        $totalLiabilitySettlements = round($totalSupplierPayments + $totalSalaryPayments, 2);

        // Account-specific outflow separation: Physical Cash Drawer vs Digital/Bank/MFS vs Unresolved/Unknown
        $activeOutflowsList = collect([...$activeExpenses, ...$activeSupplierPayments, ...$activeSalaryPayments, ...$activeAdvances]);
        $totalAccountOutflows = round($activeOutflowsList->sum('amount'), 2);
        $actualCashOutflows = round($activeOutflowsList->where('account_type', 'cash')->sum('amount'), 2);
        $digitalBankOutflows = round($activeOutflowsList->whereIn('account_type', ['bank', 'bkash', 'nagad'])->sum('amount'), 2);
        $unresolvedAccountOutflows = round($activeOutflowsList->whereNotIn('account_type', ['cash', 'bank', 'bkash', 'nagad'])->sum('amount'), 2);

        return Inertia::render('Reports/Transactions', [
            'mode' => $mode,
            'selectedDate' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'activeTab' => $activeTab,
            'categoryFilter' => $categoryFilter,
            'purchaseLines' => $purchaseLines,
            'allOutflows' => $allOutflows,
            'summary' => [
                'total_purchases' => $totalPurchases,
                'total_raw_materials' => $totalRawMaterials,
                'total_packaging' => $totalPackaging,
                'total_consumables' => $totalConsumables,
                'total_operating_expenses' => $totalOperatingExpenses,
                'total_supplier_payments' => $totalSupplierPayments,
                'total_salary_payments' => $totalSalaryPayments,
                'total_advances_issued' => $totalAdvancesIssued,
                'total_liability_settlements' => $totalLiabilitySettlements,
                'total_account_outflows' => $totalAccountOutflows,
                'actual_cash_outflows' => $actualCashOutflows,
                'digital_bank_outflows' => $digitalBankOutflows,
                'unresolved_account_outflows' => $unresolvedAccountOutflows,
                'total_cash_outflows' => $totalAccountOutflows, // Deprecated compatibility alias. Use total_account_outflows or actual_cash_outflows.
            ],
        ]);
    }
}
