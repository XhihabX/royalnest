<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use App\Services\FinancialAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DailySalesController extends Controller
{
    public function __construct(
        protected FinancialAccountService $financialAccountService
    ) {}

    public function index(): Response
    {
        $sales = DailySale::with('creator')
            ->orderBy('sales_date', 'desc')
            ->limit(60)
            ->get();

        return Inertia::render('Sales/Daily', [
            'sales' => $sales,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sales_date' => 'required|date',
            'total_sales' => 'required|numeric|min:0',
            'cash_sales' => 'nullable|numeric|min:0',
            'bkash_sales' => 'nullable|numeric|min:0',
            'nagad_sales' => 'nullable|numeric|min:0',
            'card_bank_sales' => 'nullable|numeric|min:0',
            'other_sales' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $total = (float)$validated['total_sales'];
        $cash = (float)($validated['cash_sales'] ?? 0);
        $bkash = (float)($validated['bkash_sales'] ?? 0);
        $nagad = (float)($validated['nagad_sales'] ?? 0);
        $card = (float)($validated['card_bank_sales'] ?? 0);
        $other = (float)($validated['other_sales'] ?? 0);

        $breakdownSum = $cash + $bkash + $nagad + $card + $other;
        $discrepancy = round($total - $breakdownSum, 2);

        DB::transaction(function () use ($validated, $total, $cash, $bkash, $nagad, $card, $other, $discrepancy) {
            $dailySale = DailySale::updateOrCreate(
                ['sales_date' => $validated['sales_date']],
                [
                    'total_sales' => $total,
                    'cash_sales' => $cash,
                    'bkash_sales' => $bkash,
                    'nagad_sales' => $nagad,
                    'card_bank_sales' => $card,
                    'other_sales' => $other,
                    'discrepancy_amount' => $discrepancy,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id() ?: 1,
                ]
            );

            // Reconcile and synchronize financial accounts:
            // 1. Revert previous transactions for this DailySale to avoid double deposits upon update
            $oldTxns = AccountTransaction::where('reference_type', DailySale::class)
                ->where('reference_id', $dailySale->id)
                ->lockForUpdate()
                ->get();

            foreach ($oldTxns as $oldTxn) {
                $acc = FinancialAccount::lockForUpdate()->find($oldTxn->financial_account_id);
                if ($acc) {
                    if ($oldTxn->type === 'deposit') {
                        $acc->current_balance = round((float)$acc->current_balance - (float)$oldTxn->amount, 2);
                    } elseif ($oldTxn->type === 'withdrawal') {
                        $acc->current_balance = round((float)$acc->current_balance + (float)$oldTxn->amount, 2);
                    }
                    $acc->save();
                }
                $oldTxn->delete();
            }

            // 2. Deposit incoming sales to corresponding financial accounts
            $salesDate = $validated['sales_date'];
            $userId = auth()->id() ?: 1;

            if ($cash > 0) {
                $cashAccount = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
                if ($cashAccount) {
                    $this->financialAccountService->deposit(
                        accountId: $cashAccount->id,
                        amount: $cash,
                        date: $salesDate,
                        referenceType: DailySale::class,
                        referenceId: $dailySale->id,
                        userId: $userId,
                        notes: "Daily cash sales intake for {$salesDate}"
                    );
                }
            }

            if ($bkash > 0) {
                $bkashAccount = FinancialAccount::where('type', 'bkash')->where('is_active', true)->first();
                if ($bkashAccount) {
                    $this->financialAccountService->deposit(
                        accountId: $bkashAccount->id,
                        amount: $bkash,
                        date: $salesDate,
                        referenceType: DailySale::class,
                        referenceId: $dailySale->id,
                        userId: $userId,
                        notes: "Daily bKash sales intake for {$salesDate}"
                    );
                }
            }

            if ($nagad > 0) {
                $nagadAccount = FinancialAccount::where('type', 'nagad')->where('is_active', true)->first();
                if ($nagadAccount) {
                    $this->financialAccountService->deposit(
                        accountId: $nagadAccount->id,
                        amount: $nagad,
                        date: $salesDate,
                        referenceType: DailySale::class,
                        referenceId: $dailySale->id,
                        userId: $userId,
                        notes: "Daily Nagad sales intake for {$salesDate}"
                    );
                }
            }

            if ($card > 0) {
                $bankAccount = FinancialAccount::where('type', 'bank')->where('is_active', true)->first();
                if ($bankAccount) {
                    $this->financialAccountService->deposit(
                        accountId: $bankAccount->id,
                        amount: $card,
                        date: $salesDate,
                        referenceType: DailySale::class,
                        referenceId: $dailySale->id,
                        userId: $userId,
                        notes: "Daily card/bank sales settlement for {$salesDate}"
                    );
                }
            }
        });

        $msg = $discrepancy == 0 
            ? 'Daily sales entry saved and cash deposited into financial accounts with ৳0 discrepancy.'
            : "Daily sales saved and deposited with discrepancy of ৳{$discrepancy} flagged for manager review.";

        return redirect()->back()->with('success', $msg);
    }
}

