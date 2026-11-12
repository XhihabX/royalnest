<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Services\FinancialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        protected FinancialAccountService $accountService
    ) {}

    public function index(): Response
    {
        $expenses = OperatingExpense::with(['paymentAccount', 'creator'])
            ->orderBy('expense_date', 'desc')
            ->limit(100)
            ->get();

        $accounts = FinancialAccount::where('is_active', true)->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|in:electricity,gas,water,rent,transport,repairs,cleaning,marketing,other',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'nullable|exists:financial_accounts,id',
            'payee' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $expense = OperatingExpense::create([
            'expense_date' => $validated['expense_date'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'payment_account_id' => $validated['payment_account_id'] ?? null,
            'payee' => $validated['payee'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id() ?: 1,
        ]);

        if (! empty($validated['payment_account_id'])) {
            $this->accountService->withdraw(
                accountId: (int) $validated['payment_account_id'],
                amount: (float) $validated['amount'],
                date: $validated['expense_date'],
                referenceType: OperatingExpense::class,
                referenceId: $expense->id,
                notes: "Operating expense: {$validated['category']}"
            );
        }

        return redirect()->back()->with('success', 'Operating expense recorded.');
    }
}
