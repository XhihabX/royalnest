<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    /**
     * Create a new purchase or opening stock batch.
     */
    public function createBatch(
        Product $product,
        float $quantity,
        float $unitCost,
        string $receivedDate,
        ?int $purchaseItemId = null,
        ?string $batchNo = null
    ): StockBatch {
        $bNo = $batchNo ?: ('BATCH-' . date('Ymd', strtotime($receivedDate)) . '-' . strtoupper(substr(uniqid(), -5)));

        return StockBatch::create([
            'product_id' => $product->id,
            'purchase_item_id' => $purchaseItemId,
            'batch_no' => $bNo,
            'received_date' => $receivedDate,
            'initial_quantity' => round($quantity, 4),
            'remaining_quantity' => round($quantity, 4),
            'unit_cost' => round($unitCost, 4),
            'is_exhausted' => false,
        ]);
    }

    /**
     * Record an immutable perpetual stock ledger entry and update product inventory.
     *
     * @throws ValidationException
     */
    public function recordMovement(
        Product $product,
        string $movementType,
        float $quantityIn,
        float $quantityOut,
        float $unitCost,
        string $transactionDate,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null,
        bool $allowNegative = false,
        ?int $stockBatchId = null
    ): StockLedger {
        return DB::transaction(function () use (
            $product,
            $movementType,
            $quantityIn,
            $quantityOut,
            $unitCost,
            $transactionDate,
            $referenceType,
            $referenceId,
            $userId,
            $notes,
            $allowNegative,
            $stockBatchId
        ) {
            // Lock product record for update to prevent race conditions
            $prod = Product::lockForUpdate()->findOrFail($product->id);

            // 1. Negative stock check
            if ($quantityOut > 0 && !$allowNegative) {
                if ((float)$prod->current_stock < (float)$quantityOut) {
                    throw ValidationException::withMessages([
                        'quantity' => "Insufficient stock for '{$prod->name}'. Available: {$prod->current_stock} {$prod->base_unit}, requested: {$quantityOut} {$prod->base_unit}."
                    ]);
                }
            }

            // 2. Handle batch creation for opening stock if not passed
            $resolvedBatchId = $stockBatchId;
            if ($quantityIn > 0 && $movementType === 'opening_stock' && !$resolvedBatchId) {
                $batch = $this->createBatch($prod, $quantityIn, $unitCost, $transactionDate, null, 'OPENING-' . date('Ymd', strtotime($transactionDate)));
                $resolvedBatchId = $batch->id;
            }

            // Reference cost: in FIFO, product cost_price stores the unit_cost of incoming or active oldest batch
            if ($unitCost > 0) {
                $prod->cost_price = round($unitCost, 4);
            }

            // 3. Calculate new stock balance
            $newBalance = round((float)$prod->current_stock + (float)$quantityIn - (float)$quantityOut, 4);
            $prod->current_stock = $newBalance;
            $prod->save();

            // 4. Create immutable stock ledger entry
            $movementQty = $quantityIn > 0 ? $quantityIn : $quantityOut;
            $totalValue = round($movementQty * $unitCost, 2);

            return StockLedger::create([
                'product_id' => $prod->id,
                'stock_batch_id' => $resolvedBatchId,
                'movement_type' => $movementType,
                'transaction_date' => $transactionDate,
                'quantity_in' => round($quantityIn, 4),
                'quantity_out' => round($quantityOut, 4),
                'balance_after' => $newBalance,
                'unit_cost' => round($unitCost, 4),
                'total_value' => $totalValue,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => $userId ?? (auth()->id() ?: 1),
                'notes' => $notes,
                'created_at' => now(),
            ]);
        });
    }
}
