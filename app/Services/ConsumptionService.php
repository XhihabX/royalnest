<?php

namespace App\Services;

use App\Models\Consumption;
use App\Models\ConsumptionItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumptionService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    /**
     * Record daily ingredient consumption.
     * Enforces Negative Stock Prevention: if stock is insufficient, throws ValidationException.
     */
    public function recordConsumption(string $date, array $items, int $userId, ?string $notes = null): Consumption
    {
        return DB::transaction(function () use ($date, $items, $userId, $notes) {
            $totalCost = 0.00;
            $validatedItems = [];

            // Pre-validation and stock checking
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $quantityUsed = (float)$item['quantity_used'];

                if ($quantityUsed <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantity used for '{$product->name}' must be greater than zero."
                    ]);
                }

                if ((float)$product->current_stock < $quantityUsed) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for '{$product->name}'. Available: {$product->current_stock} {$product->base_unit}, requested: {$quantityUsed} {$product->base_unit}."
                    ]);
                }

                $unitCost = (float)$product->cost_price;
                $itemCost = round($quantityUsed * $unitCost, 2);
                $totalCost += $itemCost;

                $validatedItems[] = [
                    'product' => $product,
                    'quantity_used' => $quantityUsed,
                    'unit_cost' => $unitCost,
                    'total_cost' => $itemCost,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $consumption = Consumption::create([
                'consumption_date' => $date,
                'total_cost' => $totalCost,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            foreach ($validatedItems as $valItem) {
                ConsumptionItem::create([
                    'consumption_id' => $consumption->id,
                    'product_id' => $valItem['product']->id,
                    'quantity_used' => $valItem['quantity_used'],
                    'unit_cost' => $valItem['unit_cost'],
                    'total_cost' => $valItem['total_cost'],
                    'notes' => $valItem['notes'],
                ]);

                // Record movement in Stock Ledger
                $this->stockLedgerService->recordMovement(
                    product: $valItem['product'],
                    movementType: 'consumption',
                    quantityIn: 0,
                    quantityOut: $valItem['quantity_used'],
                    unitCost: $valItem['unit_cost'],
                    transactionDate: $date,
                    referenceType: Consumption::class,
                    referenceId: $consumption->id,
                    userId: $userId,
                    notes: $valItem['notes'] ?: "Daily consumption for {$date}"
                );
            }

            return $consumption;
        });
    }
}

