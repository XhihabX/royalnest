<?php

namespace App\Http\Controllers;

use App\Models\DailySale;
use App\Models\EmployeeAdvance;
use App\Models\OperatingExpense;
use App\Models\Purchase;
use App\Models\SalaryPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\ReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TransactionReversalController extends Controller
{
    public function __construct(protected ReversalService $reversalService) {}

    public function voidPurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'allow_partial_return' => 'nullable|boolean',
        ]);

        $result = $this->reversalService->voidPurchase(
            $purchase,
            $validated['reason'],
            (bool) ($validated['allow_partial_return'] ?? false)
        );

        return back()->with('success', $result['message']);
    }

    public function voidSupplierPayment(Request $request, SupplierPayment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->reversalService->voidSupplierPayment($payment, $validated['reason']);

        return back()->with('success', "Payment voucher #{$payment->voucher_no} successfully voided.");
    }

    public function recordSupplierRefund(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'receiving_account_id' => 'required|exists:financial_accounts,id',
            'refund_date' => 'required|date',
            'reference' => 'required|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->reversalService->recordSupplierRefund(
            $supplier,
            (float) $validated['amount'],
            (int) $validated['receiving_account_id'],
            $validated['refund_date'],
            $validated['reference'],
            $validated['notes'] ?? 'Cash refund from supplier'
        );

        return back()->with('success', "Refund of ৳{$validated['amount']} from {$supplier->company_name} successfully recorded.");
    }

    public function voidSalaryPayment(Request $request, SalaryPayment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->reversalService->voidSalaryPayment($payment, $validated['reason']);

        return back()->with('success', 'Salary payment successfully voided.');
    }

    public function voidEmployeeAdvance(Request $request, EmployeeAdvance $advance): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->reversalService->voidEmployeeAdvance($advance, $validated['reason']);

        return back()->with('success', "Advance voucher #{$advance->voucher_no} successfully voided.");
    }

    public function recordAdvanceRepayment(Request $request, EmployeeAdvance $advance): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'receiving_account_id' => 'required|exists:financial_accounts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->reversalService->recordAdvanceRepayment(
            $advance,
            (float) $validated['amount'],
            (int) $validated['receiving_account_id'],
            $validated['notes'] ?? 'Advance cash repayment'
        );

        return back()->with('success', "Cash advance repayment of ৳{$validated['amount']} recorded.");
    }

    public function voidExpense(Request $request, OperatingExpense $expense): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->reversalService->voidOperatingExpense($expense, $validated['reason']);

        return back()->with('success', "Expense entry #{$expense->id} successfully voided.");
    }

    public function voidDailySale(Request $request, DailySale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->reversalService->voidDailySale($sale, $validated['reason']);

        return back()->with('success', "Daily sales entry for {$sale->sales_date} successfully voided.");
    }
}
