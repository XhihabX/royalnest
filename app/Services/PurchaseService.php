<?php

namespace App\Services;

use App\Models\Consumption;
use App\Models\ConsumptionItem;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService,
        protected FinancialAccountService $financialAccountService
    ) {}

    /**
     * Confirm a purchase invoice, convert units to base unit, create FIFO stock batch,
     * update perpetual stock ledger, and handle the direct "Purchase & Consume" workflow if enabled.
     */
    public function confirmPurchase(array $purchaseData, array $items, int $userId): Purchase
    {
        return DB::transaction(function () use ($purchaseData, $items, $userId) {
            $totalAmount = 0.00;
            $processedItems = [];

            // 1. Calculate items, convert to base unit
            foreach ($items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $purchaseQty = (float)$itemData['purchase_quantity'];
                $purchaseUnit = trim($itemData['purchase_unit']);
                $unitPrice = (float)$itemData['unit_price'];
                $subtotal = round($purchaseQty * $unitPrice, 2);
                $totalAmount += $subtotal;

                // Unit conversion to base unit
                $multiplier = 1.0;
                if (strtolower($purchaseUnit) !== strtolower($product->base_unit)) {
                    $conv = ProductUnitConversion::where('product_id', $product->id)
                        ->where('purchase_unit', $purchaseUnit)
                        ->first();
                    
                    if (!$conv) {
                        throw ValidationException::withMessages([
                            'items' => "No unit conversion defined for product '{$product->name}' from '{$purchaseUnit}' to base unit '{$product->base_unit}'."
                        ]);
                    }
                    $multiplier = (float)$conv->multiplier_to_base;
                }

                $baseQuantity = round($purchaseQty * $multiplier, 4);
                if ($baseQuantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Base quantity for '{$product->name}' must be greater than zero."
                    ]);
                }

                $baseUnitCost = round($subtotal / $baseQuantity, 4);

                $processedItems[] = [
                    'product' => $product,
                    'purchase_quantity' => $purchaseQty,
                    'purchase_unit' => $purchaseUnit,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'base_quantity' => $baseQuantity,
                    'base_unit_cost' => $baseUnitCost,
                ];
            }

            // 2. Create Purchase record
            $paidAmount = isset($purchaseData['paid_amount']) ? (float)$purchaseData['paid_amount'] : 0.00;
            $isPurchaseAndConsume = !empty($purchaseData['is_purchase_and_consume']);

            $purchase = Purchase::create([
                'supplier_id' => $purchaseData['supplier_id'] ?? null,
                'invoice_no' => $purchaseData['invoice_no'] ?? ('PUR-' . strtoupper(uniqid())),
                'purchase_date' => $purchaseData['purchase_date'] ?? now()->toDateString(),
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'status' => 'confirmed',
                'is_purchase_and_consume' => $isPurchaseAndConsume,
                'notes' => $purchaseData['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // If direct Purchase & Consume, prepare linked Consumption record
            $consumption = null;
            if ($isPurchaseAndConsume) {
                $consumption = Consumption::create([
                    'consumption_date' => $purchase->purchase_date,
                    'total_cost' => $totalAmount,
                    'notes' => "Direct Purchase & Consume from Purchase #{$purchase->invoice_no}",
                    'created_by' => $userId,
                ]);
            }

            // 3. Create items, create FIFO StockBatches, and post to Stock Ledger
            foreach ($processedItems as $pItem) {
                $product = $pItem['product'];

                $purchaseItem = PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'purchase_quantity' => $pItem['purchase_quantity'],
                    'purchase_unit' => $pItem['purchase_unit'],
                    'unit_price' => $pItem['unit_price'],
                    'subtotal' => $pItem['subtotal'],
                    'base_quantity' => $pItem['base_quantity'],
                    'base_unit_cost' => $pItem['base_unit_cost'],
                ]);

                // Create independent FIFO StockBatch for this purchase line
                $batchNo = 'BATCH-' . $purchase->purchase_date->format('Ymd') . '-' . $purchaseItem->id;
                $batch = $this->stockLedgerService->createBatch(
                    product: $product,
                    quantity: $pItem['base_quantity'],
                    unitCost: $pItem['base_unit_cost'],
                    receivedDate: $purchase->purchase_date->toDateString(),
                    purchaseItemId: $purchaseItem->id,
                    batchNo: $batchNo
                );

                // Movement 1: Stock Receipt (+Q)
                $this->stockLedgerService->recordMovement(
                    product: $product,
                    movementType: 'purchase_receipt',
                    quantityIn: $pItem['base_quantity'],
                    quantityOut: 0,
                    unitCost: $pItem['base_unit_cost'],
                    transactionDate: $purchase->purchase_date->toDateString(),
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    userId: $userId,
                    notes: "Purchase receipt for Inv #{$purchase->invoice_no} ({$batchNo})",
                    allowNegative: false,
                    stockBatchId: $batch->id
                );

                // Movement 2: If Direct Purchase & Consume (-Q)
                if ($isPurchaseAndConsume) {
                    // Exhaust this specific batch immediately
                    $batch->remaining_quantity = 0.0000;
                    $batch->is_exhausted = true;
                    $batch->save();

                    $this->stockLedgerService->recordMovement(
                        product: $product,
                        movementType: 'consumption',
                        quantityIn: 0,
                        quantityOut: $pItem['base_quantity'],
                        unitCost: $pItem['base_unit_cost'],
                        transactionDate: $purchase->purchase_date->toDateString(),
                        referenceType: Purchase::class,
                        referenceId: $purchase->id,
                        userId: $userId,
                        notes: "Immediate consumption for Inv #{$purchase->invoice_no} ({$batchNo})",
                        allowNegative: false,
                        stockBatchId: $batch->id
                    );

                    ConsumptionItem::create([
                        'consumption_id' => $consumption->id,
                        'product_id' => $product->id,
                        'stock_batch_id' => $batch->id,
                        'quantity_used' => $pItem['base_quantity'],
                        'unit_cost' => $pItem['base_unit_cost'],
                        'total_cost' => $pItem['subtotal'],
                        'notes' => 'Direct Purchase & Consume',
                    ]);
                }
            }

            // 4. Update supplier payable if applicable
            $unpaidAmount = round($totalAmount - $paidAmount, 2);
            if ($purchase->supplier_id && $unpaidAmount > 0) {
                Supplier::where('id', $purchase->supplier_id)->increment('current_balance', $unpaidAmount);
            }

            // 5. Deduct paid amount from financial account if provided
            if ($paidAmount > 0 && !empty($purchaseData['payment_account_id'])) {
                $this->financialAccountService->withdraw(
                    accountId: (int)$purchaseData['payment_account_id'],
                    amount: $paidAmount,
                    date: $purchase->purchase_date->toDateString(),
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    userId: $userId,
                    notes: "Payment for Purchase #{$purchase->invoice_no}"
                );
            }

            return $purchase;
        });
    }
}
