<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\SavedCostTemplate;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SmartCostService
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected ConsumptionService $consumptionService,
        protected SupplierService $supplierService,
        protected EmployeeService $employeeService,
        protected FinancialAccountService $financialAccountService
    ) {}

    /**
     * Get categorized saved cost templates for lightning-fast selection.
     *
     * @return array{
     *     frequently_used: Collection,
     *     recently_used: Collection,
     *     favorites: Collection,
     *     all: Collection
     * }
     */
    public function getTemplates(?string $search = null): array
    {
        $query = SavedCostTemplate::where('is_active', true)
            ->with(['category', 'product', 'supplier', 'employee', 'defaultPaymentAccount']);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $all = (clone $query)->orderBy('name')->get();
        $favorites = (clone $query)->where('is_favorite', true)->orderBy('name')->get();
        $frequentlyUsed = (clone $query)->where('use_count', '>', 0)->orderByDesc('use_count')->limit(8)->get();
        $recentlyUsed = (clone $query)->whereNotNull('last_used_at')->orderByDesc('last_used_at')->limit(8)->get();

        return [
            'frequently_used' => $frequentlyUsed,
            'recently_used' => $recentlyUsed,
            'favorites' => $favorites,
            'all' => $all,
        ];
    }

    /**
     * Record a smart cost entry executing its distinct underlying business workflow.
     */
    public function recordCost(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $type = $data['transaction_type'];
            $date = $data['transaction_date'] ?? now()->toDateString();
            $result = [];

            switch ($type) {
                case 'purchase':
                    $result = $this->handlePurchase($data, $date, $userId);
                    break;

                case 'consumption':
                    $result = $this->handleConsumption($data, $date, $userId);
                    break;

                case 'expense':
                    $result = $this->handleExpense($data, $date, $userId);
                    break;

                case 'salary_payment':
                    $result = $this->handleSalaryPayment($data, $date, $userId);
                    break;

                case 'salary_advance':
                    $result = $this->handleSalaryAdvance($data, $date, $userId);
                    break;

                case 'supplier_payment':
                    $result = $this->handleSupplierPayment($data, $date, $userId);
                    break;

                case 'other':
                    $result = $this->handleOtherPayment($data, $date, $userId);
                    break;

                default:
                    throw ValidationException::withMessages([
                        'transaction_type' => "Unknown transaction type: {$type}",
                    ]);
            }

            // If user requested to save or update this item as a shortcut template
            if (! empty($data['save_as_template'])) {
                $this->saveOrUpdateTemplate($data, $userId);
            } elseif (! empty($data['template_id'])) {
                $template = SavedCostTemplate::find($data['template_id']);
                if ($template) {
                    $template->increment('use_count');
                    $template->update([
                        'last_used_at' => now(),
                        'last_unit_price' => ! empty($data['unit_price']) ? round((float) $data['unit_price'], 4) : $template->last_unit_price,
                    ]);
                }
            }

            return $result;
        });
    }

    protected function handlePurchase(array $data, string $date, int $userId): array
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $product = Product::findOrFail($productId);
        $quantity = (float) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? $product->cost_price);
        $totalAmount = round($quantity * $unitPrice, 2);
        $paidAmount = isset($data['paid_amount']) ? round((float) $data['paid_amount'], 2) : $totalAmount;
        $accountId = ! empty($data['payment_account_id']) ? (int) $data['payment_account_id'] : null;
        $supplierId = ! empty($data['supplier_id']) ? (int) $data['supplier_id'] : ($product->supplier_id ?? null);

        if (! $supplierId) {
            $supplier = Supplier::where('is_active', true)->first();
            $supplierId = $supplier ? $supplier->id : 1;
        }

        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $supplierId,
                'invoice_no' => $data['reference'] ?? ('PUR-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4))),
                'purchase_date' => $date,
                'paid_amount' => $paidAmount,
                'payment_account_id' => $paidAmount > 0 ? $accountId : null,
                'is_purchase_and_consume' => ! empty($data['is_purchase_and_consume']),
                'notes' => $data['notes'] ?? 'Smart Cost Entry purchase',
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => $quantity,
                    'purchase_unit' => $data['unit'] ?? $product->purchase_unit,
                    'unit_price' => $unitPrice,
                ],
            ],
            userId: $userId
        );

        return [
            'type' => 'purchase',
            'message' => "Stock purchase of {$quantity} {$data['unit']} {$product->name} (৳{$totalAmount}) recorded successfully.",
            'purchase_id' => $purchase->id,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
        ];
    }

    protected function handleConsumption(array $data, string $date, int $userId): array
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 1);

        $consumption = $this->consumptionService->recordConsumption(
            date: $date,
            items: [
                [
                    'product_id' => $productId,
                    'quantity_used' => $quantity,
                    'notes' => $data['notes'] ?? 'Smart cost direct usage',
                ],
            ],
            notes: $data['notes'] ?? 'Daily Cost kitchen consumption',
            userId: $userId
        );

        return [
            'type' => 'consumption',
            'message' => "Ingredient usage of {$quantity} recorded in FIFO ledger.",
            'consumption_id' => $consumption->id,
            'total_cost' => (float) $consumption->total_cost,
        ];
    }

    protected function handleExpense(array $data, string $date, int $userId): array
    {
        $amount = round((float) ($data['amount'] ?? ($data['quantity'] * ($data['unit_price'] ?? 1))), 2);
        $category = $data['expense_category'] ?? 'misc';
        $accountId = (int) ($data['payment_account_id'] ?? 0);

        if (! $accountId) {
            $defaultAcc = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
            $accountId = $defaultAcc ? $defaultAcc->id : 1;
        }

        $expense = OperatingExpense::create([
            'expense_date' => $date,
            'category' => $category,
            'amount' => $amount,
            'financial_account_id' => $accountId,
            'payee' => $data['payee'] ?? ($data['name'] ?? null),
            'notes' => $data['notes'] ?? 'Smart Cost Operating Expense',
            'created_by' => $userId,
        ]);

        $this->financialAccountService->withdraw(
            accountId: $accountId,
            amount: $amount,
            date: $date,
            referenceType: OperatingExpense::class,
            referenceId: $expense->id,
            userId: $userId,
            notes: "Operating expense ({$category}): ".($data['notes'] ?? 'Daily cost payment')
        );

        return [
            'type' => 'expense',
            'message' => "Operating expense of ৳{$amount} ({$category}) recorded and account updated.",
            'expense_id' => $expense->id,
            'amount' => $amount,
        ];
    }

    protected function handleSalaryPayment(array $data, string $date, int $userId): array
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $accountId = (int) ($data['payment_account_id'] ?? 0);
        $monthYear = $data['salary_month'] ?? date('Y-m', strtotime($date));
        $recoverAdvances = ! empty($data['recover_advances']);

        if (! $accountId) {
            $defaultAcc = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
            $accountId = $defaultAcc ? $defaultAcc->id : 1;
        }

        $payment = $this->employeeService->disburseSalary(
            employeeId: $employeeId,
            grossAmount: $amount,
            paymentDate: $date,
            paymentAccountId: $accountId,
            paymentType: 'salary_payment',
            monthYear: $monthYear,
            advanceDeduction: 0.00,
            notes: $data['notes'] ?? 'Salary payment from Daily Cost Entry',
            userId: $userId
        );

        return [
            'type' => 'salary_payment',
            'message' => "Salary payment of ৳{$amount} disbursed successfully.",
            'payment_id' => $payment->id,
            'amount' => $amount,
        ];
    }

    protected function handleSalaryAdvance(array $data, string $date, int $userId): array
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $accountId = (int) ($data['payment_account_id'] ?? 0);

        if (! $accountId) {
            $defaultAcc = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
            $accountId = $defaultAcc ? $defaultAcc->id : 1;
        }

        $advance = $this->employeeService->issueAdvance(
            employeeId: $employeeId,
            amount: $amount,
            advanceDate: $date,
            paymentAccountId: $accountId,
            paymentMethod: 'cash',
            reason: $data['notes'] ?? 'Staff advance from Daily Cost Entry',
            userId: $userId
        );

        return [
            'type' => 'salary_advance',
            'message' => "Staff cash advance of ৳{$amount} issued and advance ledger updated.",
            'advance_id' => $advance->id,
            'amount' => $amount,
        ];
    }

    protected function handleSupplierPayment(array $data, string $date, int $userId): array
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $accountId = (int) ($data['payment_account_id'] ?? 0);

        if (! $accountId) {
            $defaultAcc = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
            $accountId = $defaultAcc ? $defaultAcc->id : 1;
        }

        $payment = $this->supplierService->paySupplierDue(
            supplierId: $supplierId,
            amount: $amount,
            paymentDate: $date,
            paymentAccountId: $accountId,
            paymentMethod: 'cash',
            notes: $data['notes'] ?? 'Supplier due payment from Daily Cost Entry',
            userId: $userId
        );

        return [
            'type' => 'supplier_payment',
            'message' => "Supplier payment of ৳{$amount} recorded and allocated against outstanding bills.",
            'payment_id' => $payment->id,
            'amount' => $amount,
        ];
    }

    protected function handleOtherPayment(array $data, string $date, int $userId): array
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $accountId = (int) ($data['payment_account_id'] ?? 0);

        if (! $accountId) {
            $defaultAcc = FinancialAccount::where('type', 'cash')->where('is_active', true)->first();
            $accountId = $defaultAcc ? $defaultAcc->id : 1;
        }

        $txn = $this->financialAccountService->withdraw(
            accountId: $accountId,
            amount: $amount,
            date: $date,
            userId: $userId,
            notes: $data['notes'] ?? 'Other financial payment'
        );

        return [
            'type' => 'other',
            'message' => "Payment of ৳{$amount} recorded from financial account.",
            'transaction_id' => $txn->id,
            'amount' => $amount,
        ];
    }

    protected function saveOrUpdateTemplate(array $data, int $userId): SavedCostTemplate
    {
        $name = trim($data['name'] ?? ($data['item_name'] ?? ''));
        if (empty($name) && ! empty($data['product_id'])) {
            $prod = Product::find($data['product_id']);
            $name = $prod ? $prod->name : 'Item';
        }

        $unitPrice = ! empty($data['unit_price']) ? round((float) $data['unit_price'], 4) : 0.0000;

        $existing = SavedCostTemplate::where('name', $name)
            ->where('transaction_type', $data['transaction_type'])
            ->first();

        if ($existing) {
            $existing->update([
                'category_id' => $data['category_id'] ?? $existing->category_id,
                'product_id' => $data['product_id'] ?? $existing->product_id,
                'supplier_id' => $data['supplier_id'] ?? $existing->supplier_id,
                'employee_id' => $data['employee_id'] ?? $existing->employee_id,
                'expense_category' => $data['expense_category'] ?? $existing->expense_category,
                'default_unit' => $data['unit'] ?? $existing->default_unit,
                'last_unit_price' => $unitPrice > 0 ? $unitPrice : $existing->last_unit_price,
                'default_payment_account_id' => $data['payment_account_id'] ?? $existing->default_payment_account_id,
                'is_favorite' => isset($data['is_favorite']) ? ! empty($data['is_favorite']) : $existing->is_favorite,
                'use_count' => $existing->use_count + 1,
                'last_used_at' => now(),
                'is_active' => true,
            ]);

            return $existing;
        }

        return SavedCostTemplate::create([
            'name' => $name,
            'transaction_type' => $data['transaction_type'],
            'category_id' => $data['category_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'expense_category' => $data['expense_category'] ?? null,
            'default_unit' => $data['unit'] ?? null,
            'last_unit_price' => $unitPrice,
            'default_payment_account_id' => $data['payment_account_id'] ?? null,
            'is_favorite' => ! empty($data['is_favorite']),
            'use_count' => 1,
            'last_used_at' => now(),
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }
}
