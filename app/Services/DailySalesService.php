<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailySalesService
{
    public function __construct(
        protected FinancialAccountService $financialAccountService
    ) {}

    /**
     * Authoritative creation or update of DailySale and atomic synchronization with financial accounts.
     *
     * @param array{
     *     sales_date: string,
     *     total_sales: float|int,
     *     cash_sales?: float|int|null,
     *     bkash_sales?: float|int|null,
     *     nagad_sales?: float|int|null,
     *     card_bank_sales?: float|int|null,
     *     other_sales?: float|int|null,
     *     notes?: string|null,
     *     source?: string|null,
     *     allow_mfs_cash_fallback?: bool|null
     * } $data
     * @return array{sale: DailySale, discrepancy: float, message: string}
     */
    public function recordDailySale(array $data, int $userId): array
    {
        $date = $data['sales_date'];
        $total = round((float) ($data['total_sales'] ?? 0), 2);
        $cash = round((float) ($data['cash_sales'] ?? 0), 2);
        $bkash = round((float) ($data['bkash_sales'] ?? 0), 2);
        $nagad = round((float) ($data['nagad_sales'] ?? 0), 2);
        $card = round((float) ($data['card_bank_sales'] ?? 0), 2);
        $other = round((float) ($data['other_sales'] ?? 0), 2);

        $breakdownSum = round($cash + $bkash + $nagad + $card + $other, 2);

        // If total_sales was not explicitly specified or 0, but channels exist, derive total
        if ($total <= 0 && $breakdownSum > 0) {
            $total = $breakdownSum;
        }

        $discrepancy = round($total - $breakdownSum, 2);
        $source = $data['source'] ?? 'manual_entry';

        // Pre-validate that all non-zero payment channels map to active financial accounts
        // Strict channel mapping: bKash, Nagad, and Bank NEVER fall back to cash.
        // Only 'other' channel falls back to cash if no dedicated 'other' account exists.
        $channelAccountMap = [
            'cash' => ['amount' => $cash, 'type' => 'cash', 'label' => 'Cash Drawer'],
            'bkash' => ['amount' => $bkash, 'type' => 'bkash', 'label' => 'bKash'],
            'nagad' => ['amount' => $nagad, 'type' => 'nagad', 'label' => 'Nagad'],
            'bank' => ['amount' => $card, 'type' => 'bank', 'label' => 'Bank/Card'],
            'other' => ['amount' => $other, 'type' => 'other', 'label' => 'Other Channel'],
        ];

        $resolvedAccounts = [];
        foreach ($channelAccountMap as $key => $info) {
            if ($info['amount'] > 0) {
                $acc = FinancialAccount::where('type', $info['type'])->where('is_active', true)->first();
                if (! $acc && $info['type'] === 'other') {
                    $acc = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
                }

                if (! $acc) {
                    throw ValidationException::withMessages([
                        "{$key}_sales" => "Cannot record {$info['label']} sales of ৳{$info['amount']}: No active financial account of type '{$info['type']}' exists.",
                    ]);
                }
                $resolvedAccounts[$key] = $acc;
            }
        }

        return DB::transaction(function () use ($date, $total, $cash, $bkash, $nagad, $card, $other, $discrepancy, $data, $source, $userId, $resolvedAccounts) {
            // 1. Fetch or initialize DailySale with row lock, safely handling potential race condition on initial insert
            $dailySale = DailySale::where('sales_date', $date)->lockForUpdate()->first();

            if (! $dailySale) {
                try {
                    $dailySale = DailySale::create([
                        'sales_date' => $date,
                        'total_sales' => $total,
                        'cash_sales' => $cash,
                        'bkash_sales' => $bkash,
                        'nagad_sales' => $nagad,
                        'card_bank_sales' => $card,
                        'other_sales' => $other,
                        'discrepancy_amount' => $discrepancy,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $userId,
                    ]);
                } catch (QueryException $e) {
                    // If another concurrent request just created the row, re-fetch with lock
                    if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'Duplicate')) {
                        $dailySale = DailySale::where('sales_date', $date)->lockForUpdate()->firstOrFail();
                    } else {
                        throw $e;
                    }
                }
            }

            if ($dailySale) {
                // If existing sale was voided, do not overwrite silently
                if ($dailySale->reversal_status === 'voided') {
                    throw ValidationException::withMessages([
                        'sales_date' => "Daily sales for {$date} was previously voided and cannot be overwritten directly.",
                    ]);
                }

                $dailySale->total_sales = $total;
                $dailySale->cash_sales = $cash;
                $dailySale->bkash_sales = $bkash;
                $dailySale->nagad_sales = $nagad;
                $dailySale->card_bank_sales = $card;
                $dailySale->other_sales = $other;
                $dailySale->discrepancy_amount = $discrepancy;
                if (! empty($data['notes'])) {
                    $dailySale->notes = $data['notes'];
                }
                $dailySale->save();
            }

            // 2. Lock & Reverse any existing active AccountTransactions referencing this DailySale
            // Immutable Ledger: Original records are NEVER deleted. A compensating entry is created,
            // the original is marked 'reversed', and full audit history is preserved.
            $existingTxns = AccountTransaction::where('reference_type', DailySale::class)
                ->where('reference_id', $dailySale->id)
                ->where('reversal_status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($existingTxns as $txn) {
                $account = FinancialAccount::where('id', $txn->financial_account_id)->lockForUpdate()->first();
                if ($account) {
                    if ($txn->type === 'deposit') {
                        $account->current_balance = round((float) $account->current_balance - (float) $txn->amount, 2);
                    } elseif ($txn->type === 'withdrawal') {
                        $account->current_balance = round((float) $account->current_balance + (float) $txn->amount, 2);
                    }
                    $account->save();
                }

                // Create compensating transaction (type opposite of original)
                $compensatingType = $txn->type === 'deposit' ? 'withdrawal' : 'deposit';
                $compensating = AccountTransaction::create([
                    'financial_account_id' => $txn->financial_account_id,
                    'transaction_date' => $date,
                    'type' => $compensatingType,
                    'amount' => $txn->amount,
                    'reference_type' => DailySale::class,
                    'reference_id' => $dailySale->id,
                    'reversal_status' => 'compensating',
                    'reversal_of_id' => $txn->id,
                    'reversed_at' => now(),
                    'reversed_by' => $userId,
                    'reversal_reason' => "Compensating entry for DailySale update on {$date}",
                    'notes' => "Compensating reversal of Txn #{$txn->id} [{$txn->type} ৳{$txn->amount}]",
                    'created_by' => $userId,
                ]);

                // Mark original transaction as reversed
                $txn->update([
                    'reversal_status' => 'reversed',
                    'reversed_at' => now(),
                    'reversed_by' => $userId,
                    'reversal_reason' => 'DailySale corrected on '.now()->toDateTimeString(),
                    'compensating_transaction_id' => $compensating->id,
                ]);
            }

            // 3. Atomically deposit incoming sales per channel into active financial accounts
            $channels = [
                'cash' => [
                    'amount' => $cash,
                    'desc' => "Daily cash sales intake for {$date} [Source: {$source}]",
                ],
                'bkash' => [
                    'amount' => $bkash,
                    'desc' => "Daily bKash sales intake for {$date} [Source: {$source}]",
                ],
                'nagad' => [
                    'amount' => $nagad,
                    'desc' => "Daily Nagad sales intake for {$date} [Source: {$source}]",
                ],
                'bank' => [
                    'amount' => $card,
                    'desc' => "Daily card/bank sales settlement for {$date} [Source: {$source}]",
                ],
                'other' => [
                    'amount' => $other,
                    'desc' => "Daily other sales channel settlement for {$date} [Source: {$source}]",
                ],
            ];

            foreach ($channels as $type => $info) {
                $amount = $info['amount'];
                if ($amount <= 0) {
                    continue;
                }

                $account = $resolvedAccounts[$type] ?? null;

                if ($account) {
                    $this->financialAccountService->deposit(
                        accountId: $account->id,
                        amount: $amount,
                        date: $date,
                        referenceType: DailySale::class,
                        referenceId: $dailySale->id,
                        userId: $userId,
                        notes: $info['desc']
                    );
                }
            }

            AuditLogger::log(
                action: $dailySale->wasRecentlyCreated ? 'created' : 'updated',
                description: "Recorded daily sales for {$date}: Total ৳{$total} (Cash: ৳{$cash}, bKash: ৳{$bkash}, Nagad: ৳{$nagad}, Card: ৳{$card}, Other: ৳{$other}) [Source: {$source}]",
                entity: $dailySale,
                module: 'sales'
            );

            $msg = $discrepancy == 0
                ? "Daily sales for {$date} saved and synchronized with financial accounts (৳0 discrepancy)."
                : "Daily sales for {$date} saved with a discrepancy of ৳{$discrepancy} flagged for review.";

            return [
                'sale' => $dailySale,
                'discrepancy' => $discrepancy,
                'message' => $msg,
            ];
        });
    }

    /**
     * Synchronize estimated cash sales from Cash Reconciliation into DailySale without corrupting non-cash channels.
     * Requires explicit confirmation if overwriting existing non-zero manual cash sales with a variance.
     */
    public function syncFromCashReconciliation(string $date, float $estimatedCashSales, int $userId, bool $confirmOverwrite = false): array
    {
        $estCash = round($estimatedCashSales, 2);

        return DB::transaction(function () use ($date, $estCash, $userId, $confirmOverwrite) {
            $existing = DailySale::where('sales_date', $date)->lockForUpdate()->first();

            if ($existing) {
                $manualCash = (float) $existing->cash_sales;
                $variance = round($estCash - $manualCash, 2);

                // If manual cash sales already exists and differs from estimated cash sales, require explicit confirmation
                if ($manualCash > 0 && abs($variance) > 0.01 && ! $confirmOverwrite) {
                    throw ValidationException::withMessages([
                        'estimated_cash_sales' => "Manual cash sales of ৳{$manualCash} already exists for {$date} (variance: ৳{$variance}). Explicit confirmation is required to overwrite manual cash sales.",
                    ]);
                }

                // Preserve existing non-cash sales
                $bkash = (float) $existing->bkash_sales;
                $nagad = (float) $existing->nagad_sales;
                $card = (float) $existing->card_bank_sales;
                $other = (float) $existing->other_sales;
                $newTotal = round($estCash + $bkash + $nagad + $card + $other, 2);

                $notes = ($existing->notes ? $existing->notes.' | ' : '').'Cash sales synchronized from Cash Reconciliation.';
            } else {
                $bkash = 0.00;
                $nagad = 0.00;
                $card = 0.00;
                $other = 0.00;
                $newTotal = $estCash;
                $notes = 'Generated from Daily Cash Reconciliation.';
            }

            return $this->recordDailySale([
                'sales_date' => $date,
                'total_sales' => $newTotal,
                'cash_sales' => $estCash,
                'bkash_sales' => $bkash,
                'nagad_sales' => $nagad,
                'card_bank_sales' => $card,
                'other_sales' => $other,
                'notes' => $notes,
                'source' => 'cash_reconciliation',
            ], $userId);
        });
    }
}
