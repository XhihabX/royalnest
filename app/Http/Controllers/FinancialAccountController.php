<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Services\FinancialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAccountController extends Controller
{
    public function __construct(
        protected FinancialAccountService $accountService
    ) {}

    public function index(): Response
    {
        $accounts = FinancialAccount::where('is_active', true)->get();
        $transactions = AccountTransaction::with(['financialAccount', 'relatedAccount', 'creator'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'transactions' => $transactions,
        ]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:financial_accounts,id',
            'to_account_id' => 'required|exists:financial_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $this->accountService->transfer(
            fromAccountId: (int)$validated['from_account_id'],
            toAccountId: (int)$validated['to_account_id'],
            amount: (float)$validated['amount'],
            date: $validated['transaction_date'],
            notes: $validated['notes'] ?? null
        );

        return redirect()->back()->with('success', 'Internal funds transfer completed successfully. Profit/Loss unaffected.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'financial_account_id' => 'required|exists:financial_accounts,id',
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validated['type'] === 'deposit') {
            $this->accountService->deposit(
                accountId: (int)$validated['financial_account_id'],
                amount: (float)$validated['amount'],
                date: $validated['transaction_date'],
                notes: $validated['notes'] ?? 'Manual deposit adjustment'
            );
        } else {
            $this->accountService->withdraw(
                accountId: (int)$validated['financial_account_id'],
                amount: (float)$validated['amount'],
                date: $validated['transaction_date'],
                notes: $validated['notes'] ?? 'Manual withdrawal adjustment'
            );
        }

        return redirect()->back()->with('success', 'Account balance adjusted.');
    }
}

