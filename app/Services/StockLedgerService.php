<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    /**
     * Record an immutable perpetual stock ledger entry and update product inventory & valuation.
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
        bool $allowNegative = false
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
            $allowNegative
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

            // 2. Calculate moving weighted-average cost on stock additions (purchase receipt, opening stock)
            if ($quantityIn > 0 && in_array($movementType, ['purchase_receipt', 'opening_stock', 'adjustment_in'])) {
                if ((float)$prod->current_stock <= 0) {
                    $prod->cost_price = round($unitCost, 4);
                } else {
                    $currentVal = (float)$prod->current_stock * (float)$prod->cost_price;
                    $incomingVal = (float)$quantityIn * (float)$unitCost;
                    $totalQty = (float)$prod->current_stock + (float)$quantityIn;
                    $prod->cost_price = round(($currentVal + $incomingVal) / $totalQty, 4);
                }
            }

            // If unitCost passed is 0 for outgoing movements, use current moving average cost
            $effectiveUnitCost = $unitCost > 0 ? $unitCost : (float)$prod->cost_price;

            // 3. Calculate new stock balance
            $newBalance = round((float)$prod->current_stock + (float)$quantityIn - (float)$quantityOut, 4);
            $prod->current_stock = $newBalance;
            $prod->save();

            // 4. Create immutable stock ledger entry
            $movementQty = $quantityIn > 0 ? $quantityIn : $quantityOut;
            $totalValue = round($movementQty * $effectiveUnitCost, 2);

            return StockLedger::create([
                'product_id' => $prod->id,
                'movement_type' => $movementType,
                'transaction_date' => $transactionDate,
                'quantity_in' => round($quantityIn, 4),
                'quantity_out' => round($quantityOut, 4),
                'balance_after' => $newBalance,
                'unit_cost' => round($effectiveUnitCost, 4),
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

