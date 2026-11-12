<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\DailyCashReconciliation;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use Carbon\Carbon;

class CashReconciliationService
{
    /**
     * Compute full daily cash reconciliation matrix and warnings for a given business date.
     *
     * @return array{
     *     date: string,
     *     reconciliation: ?DailyCashReconciliation,
     *     yesterday_closing_cash: float,
     *     opening_cash: float,
     *     other_cash_receipts: float,
     *     today_cash_payments: float,
     *     payments_breakdown: array,
     *     receipts_breakdown: array,
     *     counted_closing_cash: ?float,
     *     estimated_cash_sales: float,
     *     today_recorded_cash_sales: float,
     *     recorded_non_cash_sales: float,
     *     estimated_total_sales: float,
     *     reconciliation_difference: float,
     *     warnings: array<string>,
     *     cash_account: ?FinancialAccount
     * }
     */
    public function getReconciliationData(string $date): array
    {
        $cashAccount = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
        $cashAccountId = $cashAccount ? $cashAccount->id : 1;

        $yesterday = Carbon::parse($date)->subDay()->toDateString();
        $yesterdayRec = DailyCashReconciliation::whereDate('reconciliation_date', $yesterday)->first();
        $yesterdayClosingCash = $yesterdayRec && $yesterdayRec->counted_closing_cash !== null
            ? (float) $yesterdayRec->counted_closing_cash
            : ($yesterdayRec ? (float) $yesterdayRec->yesterday_closing_cash : 0.00);

        $existing = DailyCashReconciliation::whereDate('reconciliation_date', $date)->first();
        $openingCash = $existing ? (float) $existing->opening_cash : $yesterdayClosingCash;

        // Cash Payments (All withdrawals from cash drawer on this date)
        $withdrawals = AccountTransaction::where('financial_account_id', $cashAccountId)
            ->whereDate('transaction_date', $date)
            ->where('type', 'withdrawal')
            ->get();

        $todayCashPayments = (float) $withdrawals->sum('amount');

        // Cash Receipts not from Daily Sales (e.g. transfers in, owner capital contribution)
        $otherReceipts = AccountTransaction::where('financial_account_id', $cashAccountId)
            ->whereDate('transaction_date', $date)
            ->where('type', 'deposit')
            ->where(function ($q) {
                $q->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', DailySale::class);
            })
            ->get();

        $otherCashReceipts = (float) $otherReceipts->sum('amount');

        // Recorded sales from DailySale module
        $dailySale = DailySale::whereDate('sales_date', $date)->first();
        $recordedCashSales = $dailySale ? (float) $dailySale->cash_sales : 0.00;
        $recordedNonCashSales = $dailySale ? (float) ($dailySale->bkash_sales + $dailySale->nagad_sales + $dailySale->card_bank_sales + $dailySale->other_sales) : 0.00;

        $countedClosing = $existing && $existing->counted_closing_cash !== null ? (float) $existing->counted_closing_cash : null;

        // Estimated Cash Sales formula:
        // Estimated Cash Sales = Closing Cash - Opening Cash - Other Cash Receipts + Cash Payments
        $estimatedCashSales = 0.00;
        if ($countedClosing !== null) {
            $estimatedCashSales = round($countedClosing - $openingCash - $otherCashReceipts + $todayCashPayments, 2);
        }

        $estimatedTotalSales = round($estimatedCashSales + $recordedNonCashSales, 2);
        $diff = round($estimatedCashSales - $recordedCashSales, 2);

        // Generate warnings
        $warnings = [];
        if ($openingCash <= 0 && $yesterdayClosingCash <= 0) {
            $warnings[] = 'Opening cash balance is zero or not confirmed. Please enter and verify the morning drawer balance.';
        }
        if ($countedClosing === null) {
            $warnings[] = 'Closing cash has not been physically counted yet. Please count the drawer cash to compute estimated sales.';
        } elseif ($estimatedCashSales < 0) {
            $warnings[] = 'Warning: Estimated cash sales is negative (৳'.number_format($estimatedCashSales, 2).'). This indicates unrecorded cash expenses or an inaccurate drawer count.';
        }
        if ($dailySale && $countedClosing !== null && abs($diff) > 0.01) {
            $warnings[] = 'Reconciliation Variance: Estimated cash sales (৳'.number_format($estimatedCashSales, 2).') differs from manually entered cash sales (৳'.number_format($recordedCashSales, 2).') by ৳'.number_format(abs($diff), 2).'.';
        }

        return [
            'date' => $date,
            'reconciliation' => $existing,
            'yesterday_closing_cash' => $yesterdayClosingCash,
            'opening_cash' => $openingCash,
            'other_cash_receipts' => $otherCashReceipts,
            'today_cash_payments' => $todayCashPayments,
            'payments_breakdown' => $withdrawals,
            'receipts_breakdown' => $otherReceipts,
            'counted_closing_cash' => $countedClosing,
            'estimated_cash_sales' => $estimatedCashSales,
            'today_recorded_cash_sales' => $recordedCashSales,
            'recorded_non_cash_sales' => $recordedNonCashSales,
            'estimated_total_sales' => $estimatedTotalSales,
            'reconciliation_difference' => $diff,
            'warnings' => $warnings,
            'cash_account' => $cashAccount,
        ];
    }

    /**
     * Save daily cash reconciliation record.
     */
    public function saveReconciliation(array $data, int $userId): DailyCashReconciliation
    {
        $date = $data['reconciliation_date'];
        $opening = round((float) ($data['opening_cash'] ?? 0), 2);
        $counted = isset($data['counted_closing_cash']) && $data['counted_closing_cash'] !== '' ? round((float) $data['counted_closing_cash'], 2) : null;
        $otherReceipts = round((float) ($data['other_cash_receipts'] ?? 0), 2);
        $payments = round((float) ($data['today_cash_payments'] ?? 0), 2);
        $yesterday = round((float) ($data['yesterday_closing_cash'] ?? 0), 2);

        $estimatedCash = $counted !== null ? round($counted - $opening - $otherReceipts + $payments, 2) : 0.00;
        $nonCashSales = round((float) ($data['recorded_non_cash_sales'] ?? 0), 2);
        $estimatedTotal = round($estimatedCash + $nonCashSales, 2);

        $dailySale = DailySale::whereDate('sales_date', $date)->first();
        $actualCash = $dailySale ? (float) $dailySale->cash_sales : 0.00;
        $difference = round($estimatedCash - $actualCash, 2);

        return DailyCashReconciliation::updateOrCreate(
            ['reconciliation_date' => $date],
            [
                'yesterday_closing_cash' => $yesterday,
                'opening_cash' => $opening,
                'opening_adjustment' => round($opening - $yesterday, 2),
                'today_cash_sales' => $actualCash,
                'other_cash_receipts' => $otherReceipts,
                'today_cash_payments' => $payments,
                'counted_closing_cash' => $counted,
                'estimated_cash_sales' => $estimatedCash,
                'recorded_non_cash_sales' => $nonCashSales,
                'estimated_total_sales' => $estimatedTotal,
                'reconciliation_difference' => $difference,
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'confirmed_by' => ($data['status'] ?? '') === 'confirmed' ? $userId : null,
            ]
        );
    }
}
