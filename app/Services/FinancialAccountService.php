<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialAccountService
{
    /**
     * Record a deposit into a financial account.
     */
    public function deposit(
        int $accountId,
        float $amount,
        string $date,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): AccountTransaction {
        return DB::transaction(function () use (
            $accountId,
            $amount,
            $date,
            $referenceType,
            $referenceId,
            $userId,
            $notes
        ) {
            $account = FinancialAccount::lockForUpdate()->findOrFail($accountId);
            if (! $account->is_active) {
                throw ValidationException::withMessages([
                    'payment_account_id' => "Financial account '{$account->name}' is inactive and cannot receive funds.",
                ]);
            }
            $account->current_balance = round((float) $account->current_balance + $amount, 2);
            $account->save();

            return AccountTransaction::create([
                'financial_account_id' => $account->id,
                'transaction_date' => $date,
                'type' => 'deposit',
                'amount' => round($amount, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $userId ?? (auth()->id() ?: 1),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Record a withdrawal from a financial account.
     */
    public function withdraw(
        int $accountId,
        float $amount,
        string $date,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null
    ): AccountTransaction {
        return DB::transaction(function () use (
            $accountId,
            $amount,
            $date,
            $referenceType,
            $referenceId,
            $userId,
            $notes
        ) {
            $account = FinancialAccount::lockForUpdate()->findOrFail($accountId);
            if (! $account->is_active) {
                throw ValidationException::withMessages([
                    'payment_account_id' => "Financial account '{$account->name}' is inactive and cannot disburse funds.",
                ]);
            }
            $account->current_balance = round((float) $account->current_balance - $amount, 2);
            $account->save();

            return AccountTransaction::create([
                'financial_account_id' => $account->id,
                'transaction_date' => $date,
                'type' => 'withdrawal',
                'amount' => round($amount, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $userId ?? (auth()->id() ?: 1),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Transfer funds between two internal accounts.
     * Crucial rule: Internal account transfers have ZERO impact on profit and loss!
     */
    public function transfer(
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        string $date,
        ?int $userId = null,
        ?string $notes = null
    ): array {
        if ($fromAccountId === $toAccountId) {
            throw ValidationException::withMessages([
                'to_account_id' => 'Origin and destination accounts must be different.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Transfer amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($fromAccountId, $toAccountId, $amount, $date, $userId, $notes) {
            $from = FinancialAccount::lockForUpdate()->findOrFail($fromAccountId);
            $to = FinancialAccount::lockForUpdate()->findOrFail($toAccountId);

            if (! $from->is_active || ! $to->is_active) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Cannot execute transfer involving an inactive financial account.',
                ]);
            }

            $from->current_balance = round((float) $from->current_balance - $amount, 2);
            $from->save();

            $to->current_balance = round((float) $to->current_balance + $amount, 2);
            $to->save();

            $uId = $userId ?? (auth()->id() ?: 1);

            $txnOut = AccountTransaction::create([
                'financial_account_id' => $from->id,
                'transaction_date' => $date,
                'type' => 'transfer_out',
                'amount' => round($amount, 2),
                'related_account_id' => $to->id,
                'created_by' => $uId,
                'notes' => $notes ?: "Internal transfer to {$to->name}",
            ]);

            $txnIn = AccountTransaction::create([
                'financial_account_id' => $to->id,
                'transaction_date' => $date,
                'type' => 'transfer_in',
                'amount' => round($amount, 2),
                'related_account_id' => $from->id,
                'created_by' => $uId,
                'notes' => $notes ?: "Internal transfer from {$from->name}",
            ]);

            return ['transfer_out' => $txnOut, 'transfer_in' => $txnIn];
        });
    }

    /**
     * Toggle active/inactive status of a financial account.
     */
    public function toggleStatus(int $accountId): FinancialAccount
    {
        $account = FinancialAccount::findOrFail($accountId);
        $account->is_active = ! $account->is_active;
        $account->save();

        return $account;
    }
}
