<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\SupplierPayment;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        protected FinancialAccountService $financialAccountService
    ) {}

    /**
     * Create a supplier in the Directory.
     * Important: Creating a supplier does NOT make it active or create financial transactions.
     */
    public function createDirectorySupplier(array $data): Supplier
    {
        $openingBalance = isset($data['opening_balance']) ? (float)$data['opening_balance'] : 0.00;

        $supplier = Supplier::create([
            'name' => trim($data['name']),
            'company_name' => !empty($data['company_name']) ? trim($data['company_name']) : null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'business_location' => $data['business_location'] ?? null,
            'category' => $data['category'] ?? 'Raw Materials',
            'status' => $data['status'] ?? 'potential',
            'preferred_payment_terms' => $data['preferred_payment_terms'] ?? 'Cash on Delivery',
            'notes' => $data['notes'] ?? null,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'is_active' => ($data['status'] ?? 'potential') === 'active',
        ]);

        // Add initial products if supplied
        if (!empty($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $prod) {
                if (!empty($prod['product_name'])) {
                    $this->addSupplierProduct($supplier, $prod);
                }
            }
        }

        return $supplier;
    }

    /**
     * Update supplier directory details.
     */
    public function updateDirectorySupplier(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name' => trim($data['name']),
            'company_name' => !empty($data['company_name']) ? trim($data['company_name']) : null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'business_location' => $data['business_location'] ?? null,
            'category' => $data['category'] ?? $supplier->category,
            'status' => $data['status'] ?? $supplier->status,
            'preferred_payment_terms' => $data['preferred_payment_terms'] ?? $supplier->preferred_payment_terms,
            'notes' => $data['notes'] ?? null,
            'is_active' => ($data['status'] ?? $supplier->status) === 'active',
        ]);

        return $supplier;
    }

    /**
     * Change supplier status (Activate, Deactivate, Archive).
     * Deactivating or archiving never deletes historical data or balances.
     */
    public function setStatus(Supplier $supplier, string $status): Supplier
    {
        if (!in_array($status, ['potential', 'active', 'inactive', 'archived'])) {
            throw ValidationException::withMessages(['status' => 'Invalid supplier status.']);
        }

        $supplier->status = $status;
        $supplier->is_active = ($status === 'active');
        $supplier->save();

        return $supplier;
    }

    /**
     * Add a supplied product with agreed supply rate.
     */
    public function addSupplierProduct(Supplier $supplier, array $productData): SupplierProduct
    {
        return SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $productData['product_id'] ?? null,
            'product_name' => trim($productData['product_name']),
            'product_category' => $productData['product_category'] ?? null,
            'purchase_unit' => trim($productData['purchase_unit']),
            'unit_price' => (float)$productData['unit_price'],
            'min_order_quantity' => isset($productData['min_order_quantity']) ? (float)$productData['min_order_quantity'] : null,
            'notes' => $productData['notes'] ?? null,
        ]);
    }

    /**
     * Delete a supplied product from catalog.
     */
    public function deleteSupplierProduct(int $supplierProductId): void
    {
        SupplierProduct::where('id', $supplierProductId)->delete();
    }

    /**
     * Record a payment against supplier due balance.
     */
    public function paySupplierDue(
        int $supplierId,
        float $amount,
        string $paymentDate,
        ?int $paymentAccountId,
        string $paymentMethod = 'cash',
        ?string $notes = null,
        int $userId = 1
    ): SupplierPayment {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $supplierId,
            $amount,
            $paymentDate,
            $paymentAccountId,
            $paymentMethod,
            $notes,
            $userId
        ) {
            $supplier = Supplier::lockForUpdate()->findOrFail($supplierId);

            $voucherNo = 'VCH-' . date('Ymd', strtotime($paymentDate)) . '-' . strtoupper(substr(uniqid(), -5));

            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_account_id' => $paymentAccountId,
                'voucher_no' => $voucherNo,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            // Deduct due atomically
            $supplier->decrement('current_balance', $amount);

            // Outflow from financial account
            if ($paymentAccountId) {
                $this->financialAccountService->withdraw(
                    accountId: $paymentAccountId,
                    amount: $amount,
                    date: $paymentDate,
                    referenceType: SupplierPayment::class,
                    referenceId: $payment->id,
                    userId: $userId,
                    notes: "Payment to supplier {$supplier->name} (Voucher #{$voucherNo})"
                );
            }

            return $payment;
        });
    }

    /**
     * Record an advance payment to a supplier.
     */
    public function recordAdvance(
        int $supplierId,
        float $amount,
        string $advanceDate,
        ?int $paymentAccountId,
        string $paymentMethod = 'cash',
        ?string $reason = null,
        int $userId = 1
    ): SupplierAdvance {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Advance amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $supplierId,
            $amount,
            $advanceDate,
            $paymentAccountId,
            $paymentMethod,
            $reason,
            $userId
        ) {
            $supplier = Supplier::lockForUpdate()->findOrFail($supplierId);

            $voucherNo = 'ADV-' . date('Ymd', strtotime($advanceDate)) . '-' . strtoupper(substr(uniqid(), -5));

            $advance = SupplierAdvance::create([
                'supplier_id' => $supplier->id,
                'advance_date' => $advanceDate,
                'amount' => $amount,
                'payment_account_id' => $paymentAccountId,
                'payment_method' => $paymentMethod,
                'voucher_no' => $voucherNo,
                'reason' => $reason,
                'remaining_balance' => $amount,
                'created_by' => $userId,
            ]);

            // Supplier advance reduces net payable due
            $supplier->decrement('current_balance', $amount);

            if ($paymentAccountId) {
                $this->financialAccountService->withdraw(
                    accountId: $paymentAccountId,
                    amount: $amount,
                    date: $advanceDate,
                    referenceType: SupplierAdvance::class,
                    referenceId: $advance->id,
                    userId: $userId,
                    notes: "Advance payment to supplier {$supplier->name} (Ref #{$voucherNo})"
                );
            }

            return $advance;
        });
    }

    /**
     * Directory View: All suppliers (Potential, Active, Inactive, Archived).
     */
    public function getDirectorySuppliers(?string $search = null, ?string $category = null, ?string $status = null): array
    {
        $query = Supplier::with(['products'])->withCount('products');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $suppliers = $query->orderBy('name')->get();

        $stats = [
            'total_directory' => Supplier::count(),
            'potential' => Supplier::where('status', 'potential')->count(),
            'active' => Supplier::where('status', 'active')->count(),
            'inactive' => Supplier::where('status', 'inactive')->count(),
            'archived' => Supplier::where('status', 'archived')->count(),
        ];

        return [
            'suppliers' => $suppliers,
            'stats' => $stats,
        ];
    }

    /**
     * Active Suppliers View: Strictly suppliers currently supplying Royal Nest.
     */
    public function getActiveSuppliers(?string $search = null): array
    {
        $query = Supplier::where('status', 'active')
            ->withCount('purchases')
            ->withSum('purchases as total_purchased', 'total_amount')
            ->withSum('payments as total_paid', 'amount')
            ->withSum('advances as total_advances', 'amount');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name')->get()->map(function ($s) {
            $s->total_purchased = (float)($s->total_purchased ?? 0);
            $s->total_paid = (float)($s->total_paid ?? 0) + (float)($s->total_advances ?? 0);
            $s->last_purchase = Purchase::where('supplier_id', $s->id)->latest('purchase_date')->value('purchase_date');
            $s->last_payment = SupplierPayment::where('supplier_id', $s->id)->latest('payment_date')->value('payment_date');
            return $s;
        });

        $totalPayable = $suppliers->sum('current_balance');
        $totalPurchased = $suppliers->sum('total_purchased');
        $totalPaid = $suppliers->sum('total_paid');

        return [
            'suppliers' => $suppliers,
            'stats' => [
                'active_count' => $suppliers->count(),
                'total_payable' => round($totalPayable, 2),
                'total_purchased' => round($totalPurchased, 2),
                'total_paid' => round($totalPaid, 2),
            ],
        ];
    }

    /**
     * Comprehensive statement for dedicated supplier profile.
     */
    public function getSupplierStatement(int $supplierId, ?string $startDate = null, ?string $endDate = null): array
    {
        $supplier = Supplier::with(['products'])->findOrFail($supplierId);

        $purchasesQuery = Purchase::with(['items.product', 'creator'])
            ->where('supplier_id', $supplier->id)
            ->where('status', 'confirmed');

        $paymentsQuery = SupplierPayment::with(['account', 'creator'])
            ->where('supplier_id', $supplier->id);

        $advancesQuery = SupplierAdvance::with(['account', 'creator'])
            ->where('supplier_id', $supplier->id);

        if ($startDate) {
            $purchasesQuery->whereDate('purchase_date', '>=', $startDate);
            $paymentsQuery->whereDate('payment_date', '>=', $startDate);
            $advancesQuery->whereDate('advance_date', '>=', $startDate);
        }
        if ($endDate) {
            $purchasesQuery->whereDate('purchase_date', '<=', $endDate);
            $paymentsQuery->whereDate('payment_date', '<=', $endDate);
            $advancesQuery->whereDate('advance_date', '<=', $endDate);
        }

        $purchases = $purchasesQuery->get();
        $payments = $paymentsQuery->get();
        $advances = $advancesQuery->get();

        // 1. Dynamic FIFO Bill Allocation:
        // Allocate payments and advances against purchases chronologically to determine exact per-bill payment status
        $allPurchases = Purchase::where('supplier_id', $supplier->id)
            ->where('status', 'confirmed')
            ->orderBy('purchase_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $allPaymentsTotal = (float)SupplierPayment::where('supplier_id', $supplier->id)->sum('amount')
            + (float)SupplierAdvance::where('supplier_id', $supplier->id)->sum('amount');
        
        $paymentPool = $allPaymentsTotal;
        $billAllocations = [];

        foreach ($allPurchases as $p) {
            $unpaidAtDelivery = max(0, (float)$p->total_amount - (float)$p->paid_amount);
            $allocatedFromPool = min($unpaidAtDelivery, $paymentPool);
            $paymentPool = max(0, round($paymentPool - $allocatedFromPool, 2));

            $totalBillPaid = round((float)$p->paid_amount + $allocatedFromPool, 2);
            $remainingBillDue = max(0, round((float)$p->total_amount - $totalBillPaid, 2));

            $status = 'unpaid';
            if ($remainingBillDue <= 0 && (float)$p->total_amount > 0) {
                $status = 'paid';
            } elseif ($totalBillPaid > 0) {
                $status = 'partially_paid';
            }

            $billAllocations[$p->id] = [
                'paid_at_delivery' => (float)$p->paid_amount,
                'allocated_payments' => $allocatedFromPool,
                'total_paid' => $totalBillPaid,
                'remaining_due' => $remainingBillDue,
                'status' => $status,
            ];
        }

        $rows = collect();

        foreach ($purchases as $p) {
            $itemSummary = $p->items->map(fn ($it) => $it->product->name . ' (' . (float)$it->purchase_quantity . ' ' . $it->purchase_unit . ' @ ৳' . (float)$it->unit_price . ')')->join(', ');
            $unpaid = (float)$p->total_amount - (float)$p->paid_amount;
            $alloc = $billAllocations[$p->id] ?? null;

            $rows->push([
                'date' => $p->purchase_date->toDateString(),
                'type' => 'purchase_bill',
                'reference' => $p->invoice_no,
                'description' => 'Goods Received: ' . ($itemSummary ?: 'Kitchen Raw Materials'),
                'bill_amount' => (float)$p->total_amount,
                'paid_amount' => (float)$p->paid_amount,
                'total_bill_paid' => $alloc ? $alloc['total_paid'] : (float)$p->paid_amount,
                'remaining_bill_due' => $alloc ? $alloc['remaining_due'] : $unpaid,
                'bill_status' => $alloc ? $alloc['status'] : ($unpaid <= 0 ? 'paid' : 'unpaid'),
                'net_effect' => $unpaid,
                'items' => $p->items,
                'created_at' => $p->created_at,
            ]);
        }

        foreach ($payments as $pay) {
            $accName = $pay->account ? $pay->account->name : ucfirst($pay->payment_method);
            $rows->push([
                'date' => $pay->payment_date->toDateString(),
                'type' => 'payment_voucher',
                'reference' => $pay->voucher_no,
                'description' => 'Payment Voucher via ' . $accName . ($pay->notes ? ' (' . $pay->notes . ')' : ''),
                'bill_amount' => 0.00,
                'paid_amount' => (float)$pay->amount,
                'net_effect' => -((float)$pay->amount),
                'items' => [],
                'created_at' => $pay->created_at,
            ]);
        }

        foreach ($advances as $adv) {
            $accName = $adv->account ? $adv->account->name : ucfirst($adv->payment_method);
            $rows->push([
                'date' => $adv->advance_date->toDateString(),
                'type' => 'advance_voucher',
                'reference' => $adv->voucher_no,
                'description' => 'Supplier Advance via ' . $accName . ($adv->reason ? ' (' . $adv->reason . ')' : ''),
                'bill_amount' => 0.00,
                'paid_amount' => (float)$adv->amount,
                'net_effect' => -((float)$adv->amount),
                'items' => [],
                'created_at' => $adv->created_at,
            ]);
        }

        $sortedRows = $rows->sortBy(fn ($r) => $r['date'] . '_' . $r['created_at'])->values();

        $runningBalance = (float)$supplier->opening_balance;
        $statementRows = [];

        foreach ($sortedRows as $row) {
            $runningBalance += $row['net_effect'];
            $row['running_due'] = round($runningBalance, 2);
            $statementRows[] = $row;
        }

        return [
            'supplier' => $supplier,
            'opening_balance' => (float)$supplier->opening_balance,
            'current_balance' => (float)$supplier->current_balance,
            'total_purchased' => round($purchases->sum('total_amount'), 2),
            'total_paid' => round($purchases->sum('paid_amount') + $payments->sum('amount') + $advances->sum('amount'), 2),
            'statement_rows' => $statementRows,
            'products' => $supplier->products,
        ];
    }
}
