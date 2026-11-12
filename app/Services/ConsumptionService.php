<?php

namespace App\Services;

use App\Models\Consumption;
use App\Models\ConsumptionItem;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumptionService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    /**
     * Record daily ingredient consumption using Automatic FIFO (First-In, First-Out) Batch Costing.
     * Enforces Negative Stock Prevention: if total on-hand stock is insufficient, throws ValidationException.
     * Deducts from the oldest available purchase batches first until exhausted.
     */
    public function recordConsumption(string $date, array $items, int $userId, ?string $notes = null): Consumption
    {
        return DB::transaction(function () use ($date, $items, $userId, $notes) {
            // 1. Pre-validation and stock checking
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $quantityUsed = (float) $item['quantity_used'];

                if ($quantityUsed <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantity used for '{$product->name}' must be greater than zero.",
                    ]);
                }

                if ((float) $product->current_stock < $quantityUsed) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for '{$product->name}'. Available: {$product->current_stock} {$product->base_unit}, requested: {$quantityUsed} {$product->base_unit}.",
                    ]);
                }
            }

            // 2. Create Consumption header
            $consumption = Consumption::create([
                'consumption_date' => $date,
                'total_cost' => 0.00,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $totalConsumptionCost = 0.00;

            // 3. FIFO Batch Consumption Traversal
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $needed = (float) $item['quantity_used'];

                // Fetch active batches ordered chronologically (FIFO: oldest received first)
                $batches = StockBatch::where('product_id', $product->id)
                    ->where('is_exhausted', false)
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('received_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                // If no batch exists (e.g. fallback), create an initial batch for on-hand stock
                if ($batches->isEmpty() && (float) $product->current_stock > 0) {
                    $fallbackBatch = $this->stockLedgerService->createBatch(
                        product: $product,
                        quantity: (float) $product->current_stock,
                        unitCost: (float) $product->cost_price,
                        receivedDate: $date,
                        batchNo: 'OPENING-AUTO-'.$product->id
                    );
                    $batches = collect([$fallbackBatch]);
                }

                foreach ($batches as $batch) {
                    if ($needed <= 0) {
                        break;
                    }

                    $rem = (float) $batch->remaining_quantity;
                    $take = min($rem, $needed);
                    $sliceUnitCost = (float) $batch->unit_cost;
                    $sliceTotalCost = round($take * $sliceUnitCost, 2);

                    // Deduct from batch
                    $batch->remaining_quantity = round($rem - $take, 4);
                    if ($batch->remaining_quantity <= 0) {
                        $batch->remaining_quantity = 0.0000;
                        $batch->is_exhausted = true;
                    }
                    $batch->save();

                    // Create stock ledger movement for this FIFO batch slice
                    $this->stockLedgerService->recordMovement(
                        product: $product,
                        movementType: 'consumption',
                        quantityIn: 0,
                        quantityOut: $take,
                        unitCost: $sliceUnitCost,
                        transactionDate: $date,
                        referenceType: Consumption::class,
                        referenceId: $consumption->id,
                        userId: $userId,
                        notes: "FIFO deduction from Batch {$batch->batch_no}",
                        allowNegative: false,
                        stockBatchId: $batch->id
                    );

                    // Record item slice
                    ConsumptionItem::create([
                        'consumption_id' => $consumption->id,
                        'product_id' => $product->id,
                        'stock_batch_id' => $batch->id,
                        'quantity_used' => $take,
                        'unit_cost' => $sliceUnitCost,
                        'total_cost' => $sliceTotalCost,
                        'notes' => $item['notes'] ?? "FIFO Batch: {$batch->batch_no}",
                    ]);

                    $totalConsumptionCost += $sliceTotalCost;
                    $needed = round($needed - $take, 4);
                }

                // If still needed (in case batches were fewer than current_stock), deduct remainder at product cost
                if ($needed > 0) {
                    $unitCost = (float) $product->cost_price;
                    $sliceTotalCost = round($needed * $unitCost, 2);

                    $this->stockLedgerService->recordMovement(
                        product: $product,
                        movementType: 'consumption',
                        quantityIn: 0,
                        quantityOut: $needed,
                        unitCost: $unitCost,
                        transactionDate: $date,
                        referenceType: Consumption::class,
                        referenceId: $consumption->id,
                        userId: $userId,
                        notes: 'FIFO usage remaining balance',
                        allowNegative: false
                    );

                    ConsumptionItem::create([
                        'consumption_id' => $consumption->id,
                        'product_id' => $product->id,
                        'quantity_used' => $needed,
                        'unit_cost' => $unitCost,
                        'total_cost' => $sliceTotalCost,
                        'notes' => 'FIFO unbatched balance',
                    ]);

                    $totalConsumptionCost += $sliceTotalCost;
                }
            }

            // Update total cost on consumption header
            $consumption->total_cost = round($totalConsumptionCost, 2);
            $consumption->save();

            return $consumption;
        });
    }
}
