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
        $accounts = FinancialAccount::orderBy('name')->get();
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

    public function toggleStatus(FinancialAccount $account): RedirectResponse
    {
        $this->accountService->toggleStatus($account->id);
        $status = $account->fresh()->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Financial account '{$account->name}' has been {$status}.");
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
            fromAccountId: (int) $validated['from_account_id'],
            toAccountId: (int) $validated['to_account_id'],
            amount: (float) $validated['amount'],
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
                accountId: (int) $validated['financial_account_id'],
                amount: (float) $validated['amount'],
                date: $validated['transaction_date'],
                notes: $validated['notes'] ?? 'Manual deposit adjustment'
            );
        } else {
            $this->accountService->withdraw(
                accountId: (int) $validated['financial_account_id'],
                amount: (float) $validated['amount'],
                date: $validated['transaction_date'],
                notes: $validated['notes'] ?? 'Manual withdrawal adjustment'
            );
        }

        return redirect()->back()->with('success', 'Account balance adjusted.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:financial_accounts,name',
            'type' => 'required|in:cash,bank,bkash,nagad,other',
            'opening_balance' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $account = $this->accountService->createAccount(
            branchId: $request->user()?->branch_id ?? 1,
            name: $validated['name'],
            type: $validated['type'],
            openingBalance: (float) ($validated['opening_balance'] ?? 0),
            currency: 'BDT'
        );

        \App\Services\AuditLogger::log(
            action: 'created',
            description: "Created financial account '{$account->name}' ({$account->type}) with opening balance ৳{$account->opening_balance}",
            entity: $account,
            module: 'accounts'
        );

        return redirect()->back()->with('success', "Account '{$account->name}' created.");
    }

    public function update(Request $request, FinancialAccount $account): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:financial_accounts,name,' . $account->id,
            'type' => 'required|in:cash,bank,bkash,nagad,other',
        ]);

        $before = $account->only(['name', 'type']);
        $account->update($validated);

        \App\Services\AuditLogger::log(
            action: 'updated',
            description: "Updated financial account '{$account->name}'",
            entity: $account,
            beforePayload: $before,
            afterPayload: $account->only(['name', 'type']),
            module: 'accounts'
        );

        return redirect()->back()->with('success', "Account '{$account->name}' updated.");
    }
}
