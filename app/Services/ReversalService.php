<?php

namespace App\Services;

use App\Models\DailySale;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalaryPayment;
use App\Models\StockBatch;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReversalService
{
    public function __construct(
        protected FinancialAccountService $accountService,
        protected StockLedgerService $stockLedgerService
    ) {}

    /**
     * Void a mistaken Purchase invoice.
     * Evaluates FIFO batches:
     * - Fully intact batches are cleanly returned and product stock is deducted.
     * - Partially consumed batches allow returning only the remaining shelf stock (Q_rem)
     *   with a credit note, but block full invoice voiding.
     * - Fully consumed/wasted batches strictly block invoice voiding.
     */
    public function voidPurchase(Purchase $purchase, string $reason, bool $allowPartialReturn = false): array
    {
        if ($purchase->reversal_status !== 'active') {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase has already been reversed or voided.',
            ]);
        }

        return DB::transaction(function () use ($purchase, $reason, $allowPartialReturn) {
            $purchase->load(['items.product', 'supplier']);
            $userId = Auth::id();
            $batchAnalysis = [];
            $hasConsumedItems = false;
            $allConsumed = true;

            // 1. Inspect all linked batches for this purchase
            foreach ($purchase->items as $item) {
                $batch = StockBatch::where('purchase_item_id', $item->id)->first();
                $initQty = (float) ($item->base_quantity ?: $item->purchase_quantity);
                $remQty = $batch ? (float) $batch->remaining_quantity : $initQty;
                $consumedQty = max(0, $initQty - $remQty);

                if ($consumedQty > 0) {
                    $hasConsumedItems = true;
                }
                if ($remQty > 0) {
                    $allConsumed = false;
                }

                $batchAnalysis[] = [
                    'item' => $item,
                    'batch' => $batch,
                    'initial_quantity' => $initQty,
                    'remaining_quantity' => $remQty,
                    'consumed_quantity' => $consumedQty,
                ];
            }

            // 2. Decision Logic
            if ($hasConsumedItems && !$allowPartialReturn) {
                AuditLogger::logFailure(
                    'void_blocked',
                    "Purchase #{$purchase->invoice_no} void blocked: stock partially or fully consumed",
                    "Stock for invoice #{$purchase->invoice_no} has already been consumed in kitchen. Full void is blocked.",
                    'purchases',
                    $purchase->invoice_no,
                    'Purchase',
                    $purchase->id
                );

                throw ValidationException::withMessages([
                    'purchase' => "Cannot void Purchase #{$purchase->invoice_no} because some of this stock has already been consumed in kitchen operations. You can record a partial return of remaining shelf stock with a supplier credit note, or adjust the kitchen consumption first.",
                ]);
            }

            if ($allConsumed) {
                throw ValidationException::withMessages([
                    'purchase' => "Cannot void Purchase #{$purchase->invoice_no} because all items from this purchase have already been cooked or consumed in kitchen operations.",
                ]);
            }

            // 3. Process return of remaining intact stock
            $totalReturnedValue = 0;
            foreach ($batchAnalysis as $info) {
                $returnQty = (float) $info['remaining_quantity'];
                if ($returnQty <= 0) {
                    continue;
                }

                $product = Product::lockForUpdate()->find($info['item']->product_id);
                if ($product) {
                    // Compensating StockLedger movement: purchase_return (also updates product current_stock)
                    $this->stockLedgerService->recordMovement(
                        product: $product,
                        movementType: 'purchase_return',
                        quantityIn: 0.0,
                        quantityOut: $returnQty,
                        unitCost: (float) $info['item']->unit_price,
                        transactionDate: now()->toDateString(),
                        referenceType: 'PurchaseVoid',
                        referenceId: $purchase->id,
                        userId: $userId ?? 1,
                        notes: "Void reversal of Purchase #{$purchase->invoice_no}: {$reason}",
                        allowNegative: false,
                        stockBatchId: $info['batch']?->id
                    );
                }

                if ($info['batch']) {
                    $info['batch']->update([
                        'remaining_quantity' => 0,
                        'is_exhausted' => true,
                    ]);
                }

                $totalReturnedValue += ($returnQty * (float) $info['item']->unit_price);
            }

            // 4. Financial ledger adjustments
            $paidAmount = (float) $purchase->paid_amount;
            $supplier = $purchase->supplier ? Supplier::lockForUpdate()->find($purchase->supplier_id) : null;

            // If cash was paid immediately and this is a mistaken entry void:
            // Neutralize the payment from the financial account
            if ($paidAmount > 0) {
                // Find associated payment account transaction if any, or default cash account
                $cashAccount = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
                if ($cashAccount) {
                    $this->accountService->deposit(
                        accountId: $cashAccount->id,
                        amount: $paidAmount,
                        date: now()->toDateString(),
                        referenceType: 'PurchaseVoid',
                        referenceId: $purchase->id,
                        notes: "Compensating deposit for voided Purchase #{$purchase->invoice_no}: {$reason}"
                    );
                }
            }

            // Reduce supplier due payable by unpaid balance
            $unpaidAmount = max(0, (float) $purchase->total_amount - $paidAmount);
            if ($supplier && $unpaidAmount > 0) {
                $supplier->decrement('current_balance', $unpaidAmount);
            }

            // 5. Update purchase record status
            $purchase->update([
                'reversal_status' => 'voided',
                'reversal_type' => 'void_mistake',
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log(
                action: 'voided',
                description: "Voided Purchase #{$purchase->invoice_no} ({$reason})",
                entity: $purchase,
                reason: $reason,
                module: 'purchases',
                transactionReference: $purchase->invoice_no
            );

            return [
                'success' => true,
                'returned_value' => $totalReturnedValue,
                'message' => "Purchase #{$purchase->invoice_no} was successfully voided.",
            ];
        });
    }

    /**
     * Void a mistaken Supplier Payment entry.
     */
    public function voidSupplierPayment(SupplierPayment $payment, string $reason): void
    {
        if ($payment->reversal_status !== 'active') {
            throw ValidationException::withMessages([
                'payment' => 'This payment voucher has already been voided or reversed.',
            ]);
        }

        DB::transaction(function () use ($payment, $reason) {
            $userId = Auth::id();
            $paymentAmount = (float) $payment->amount;

            // 1. Re-add payment amount to Supplier due payable
            $supplier = Supplier::lockForUpdate()->find($payment->supplier_id);
            if ($supplier) {
                $supplier->increment('current_balance', $paymentAmount);
            }

            // 2. Unwind bill allocations
            $payment->load('allocations.purchase');
            foreach ($payment->allocations as $alloc) {
                if ($alloc->purchase) {
                    $purchase = $alloc->purchase;
                    $newPaid = max(0, (float) $purchase->paid_amount - (float) $alloc->allocated_amount);
                    $newStatus = $newPaid <= 0 ? 'due' : 'partial';
                    $purchase->update([
                        'paid_amount' => $newPaid,
                        'status' => $newStatus,
                    ]);
                }
            }

            // 3. Compensating deposit back to FinancialAccount
            if ($payment->payment_account_id) {
                $this->accountService->deposit(
                    accountId: $payment->payment_account_id,
                    amount: $paymentAmount,
                    date: now()->toDateString(),
                    referenceType: 'SupplierPaymentVoid',
                    referenceId: $payment->id,
                    notes: "Compensating refund for voided Supplier Payment #{$payment->voucher_no}: {$reason}"
                );
            }

            // 4. Mark payment as voided
            $payment->update([
                'reversal_status' => 'voided',
                'reversal_type' => 'void_mistake',
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log(
                action: 'voided',
                description: "Voided Supplier Payment #{$payment->voucher_no} of ৳{$paymentAmount} ({$reason})",
                entity: $payment,
                reason: $reason,
                module: 'suppliers',
                transactionReference: $payment->voucher_no
            );
        });
    }

    /**
     * Record an Actual Money Refund received from a supplier.
     */
    public function recordSupplierRefund(
        Supplier $supplier,
        float $amount,
        int $receivingAccountId,
        string $refundDate,
        string $reference,
        string $notes
    ): void {
        DB::transaction(function () use ($supplier, $amount, $receivingAccountId, $refundDate, $reference, $notes) {
            $account = FinancialAccount::lockForUpdate()->findOrFail($receivingAccountId);

            if (!$account->is_active) {
                throw ValidationException::withMessages([
                    'receiving_account_id' => 'The selected receiving account is inactive.',
                ]);
            }

            // Physical money received: deposit into financial account
            $this->accountService->deposit(
                accountId: $account->id,
                amount: $amount,
                date: $refundDate,
                referenceType: 'SupplierRefund',
                referenceId: $supplier->id,
                notes: "Supplier Cash Refund from {$supplier->company_name} (Ref: {$reference}): {$notes}"
            );

            // Re-credit supplier ledger
            $supplier->increment('current_balance', $amount);

            AuditLogger::log(
                action: 'refund_received',
                description: "Received ৳{$amount} cash refund from {$supplier->company_name} into {$account->name} (Ref: {$reference})",
                entity: $supplier,
                reason: $notes,
                module: 'suppliers',
                transactionReference: $reference
            );
        });
    }

    /**
     * Void a mistaken Salary Payment entry.
     */
    public function voidSalaryPayment(SalaryPayment $payment, string $reason): void
    {
        if ($payment->reversal_status !== 'active') {
            throw ValidationException::withMessages([
                'payment' => 'This salary payment has already been voided or reversed.',
            ]);
        }

        DB::transaction(function () use ($payment, $reason) {
            $userId = Auth::id();
            $amount = (float) $payment->amount;

            // 1. Compensating deposit to FinancialAccount
            if ($payment->payment_account_id) {
                $this->accountService->deposit(
                    accountId: $payment->payment_account_id,
                    amount: $amount,
                    date: now()->toDateString(),
                    referenceType: 'SalaryPaymentVoid',
                    referenceId: $payment->id,
                    notes: "Compensating deposit for voided Salary Payment (EMP ID #{$payment->employee_id}): {$reason}"
                );
            }

            // 2. Mark payment as voided
            $payment->update([
                'reversal_status' => 'voided',
                'reversal_type' => 'void_mistake',
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log(
                action: 'voided',
                description: "Voided Salary Payment of ৳{$amount} for Employee #{$payment->employee_id} ({$reason})",
                entity: $payment,
                reason: $reason,
                module: 'staff',
                transactionReference: "SAL-PAY-{$payment->id}"
            );
        });
    }

    /**
     * Void a mistaken Staff Advance entry.
     */
    public function voidEmployeeAdvance(EmployeeAdvance $advance, string $reason): void
    {
        if ($advance->reversal_status !== 'active') {
            throw ValidationException::withMessages([
                'advance' => 'This staff advance has already been voided or reversed.',
            ]);
        }

        // Check if advance was already recovered
        $initialAmount = (float) $advance->amount;
        $remaining = (float) $advance->remaining_balance;
        if ($remaining < $initialAmount) {
            throw ValidationException::withMessages([
                'advance' => "Cannot void Advance #{$advance->voucher_no} because ৳" . ($initialAmount - $remaining) . " has already been recovered in finalized salary deductions.",
            ]);
        }

        DB::transaction(function () use ($advance, $reason, $initialAmount) {
            $userId = Auth::id();

            // 1. Compensating deposit to FinancialAccount
            if ($advance->payment_account_id) {
                $this->accountService->deposit(
                    accountId: $advance->payment_account_id,
                    amount: $initialAmount,
                    date: now()->toDateString(),
                    referenceType: 'EmployeeAdvanceVoid',
                    referenceId: $advance->id,
                    notes: "Compensating deposit for voided Advance #{$advance->voucher_no}: {$reason}"
                );
            }

            // 2. Mark advance as voided
            $advance->update([
                'remaining_balance' => 0,
                'status' => 'cancelled',
                'reversal_status' => 'voided',
                'reversal_type' => 'void_mistake',
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log(
                action: 'voided',
                description: "Voided Staff Advance #{$advance->voucher_no} of ৳{$initialAmount} ({$reason})",
                entity: $advance,
                reason: $reason,
                module: 'staff',
                transactionReference: $advance->voucher_no
            );
        });
    }

    /**
     * Record an Actual Cash Repayment of an advance by an employee.
     */
    public function recordAdvanceRepayment(
        EmployeeAdvance $advance,
        float $amount,
        int $receivingAccountId,
        string $notes
    ): void {
        DB::transaction(function () use ($advance, $amount, $receivingAccountId, $notes) {
            $account = FinancialAccount::lockForUpdate()->findOrFail($receivingAccountId);

            if (!$account->is_active) {
                throw ValidationException::withMessages([
                    'receiving_account_id' => 'The selected receiving account is inactive.',
                ]);
            }

            if ($amount > (float) $advance->remaining_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Repayment amount exceeds remaining advance balance of ৳' . $advance->remaining_balance,
                ]);
            }

            $this->accountService->deposit(
                accountId: $account->id,
                amount: $amount,
                date: now()->toDateString(),
                referenceType: 'AdvanceRepayment',
                referenceId: $advance->id,
                notes: "Employee Cash Advance Repayment for #{$advance->voucher_no}: {$notes}"
            );

            $newBalance = max(0, (float) $advance->remaining_balance - $amount);
            $newStatus = $newBalance <= 0 ? 'recovered' : 'active';
            $advance->update([
                'remaining_balance' => $newBalance,
                'status' => $newStatus,
            ]);

            AuditLogger::log(
                action: 'repayment_received',
                description: "Received ৳{$amount} cash advance repayment from Employee #{$advance->employee_id} into {$account->name}",
                entity: $advance,
                reason: $notes,
                module: 'staff',
                transactionReference: $advance->voucher_no
            );
        });
    }

    /**
     * Void a mistaken Operating Expense entry.
     */
    public function voidOperatingExpense(OperatingExpense $expense, string $reason): void
    {
        if ($expense->reversal_status !== 'active') {
            throw ValidationException::withMessages([
                'expense' => 'This expense has already been voided or reversed.',
            ]);
        }

        DB::transaction(function () use ($expense, $reason) {
            $userId = Auth::id();
            $amount = (float) $expense->amount;

            // 1. Compensating deposit to FinancialAccount
            if ($expense->payment_account_id) {
                $this->accountService->deposit(
                    accountId: $expense->payment_account_id,
                    amount: $amount,
                    date: now()->toDateString(),
                    referenceType: 'ExpenseVoid',
                    referenceId: $expense->id,
                    notes: "Compensating deposit for voided Expense #{$expense->id} ({$expense->category}): {$reason}"
                );
            }

            // 2. Mark expense as voided
            $expense->update([
                'reversal_status' => 'voided',
                'reversal_type' => 'void_mistake',
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log(
                action: 'voided',
                description: "Voided Expense #{$expense->id} of ৳{$amount} ({$expense->category}): {$reason}",
                entity: $expense,
                reason: $reason,
                module: 'expenses',
                transactionReference: "EXP-{$expense->id}"
            );
        });
    }

    /**
     * Void a mistaken Daily Sales entry.
     */
    public function voidDailySale(DailySale $sale, string $reason): void
    {
        if ($sale->reversal_status !== 'active') {
            throw ValidationException::withMessages([
                'sale' => 'This daily sales entry has already been voided or reversed.',
            ]);
        }

        DB::transaction(function () use ($sale, $reason) {
            $userId = Auth::id();

            // Reverse deposits made across payment accounts
            $accountMapping = [
                'cash' => (float) $sale->cash_sales,
                'bkash' => (float) $sale->bkash_sales,
                'nagad' => (float) $sale->nagad_sales,
                'bank' => (float) $sale->card_bank_sales,
            ];

            foreach ($accountMapping as $type => $amount) {
                if ($amount > 0) {
                    $account = FinancialAccount::where('type', $type)->where('is_active', true)->first();
                    if ($account) {
                        $this->accountService->withdraw(
                            accountId: $account->id,
                            amount: $amount,
                            date: now()->toDateString(),
                            referenceType: 'DailySalesVoid',
                            referenceId: $sale->id,
                            notes: "Compensating withdrawal for voided Daily Sales ({$sale->sales_date}): {$reason}"
                        );
                    }
                }
            }

            $sale->update([
                'reversal_status' => 'voided',
                'reversal_type' => 'void_mistake',
                'reversed_at' => now(),
                'reversed_by' => $userId,
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log(
                action: 'voided',
                description: "Voided Daily Sales for {$sale->sales_date} (Total: ৳{$sale->total_sales}): {$reason}",
                entity: $sale,
                reason: $reason,
                module: 'sales',
                transactionReference: "SAL-{$sale->sales_date}"
            );
        });
    }
}
