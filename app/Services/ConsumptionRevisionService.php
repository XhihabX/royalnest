<?php

namespace App\Services;

use App\Models\Consumption;
use App\Models\ConsumptionItem;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumptionRevisionService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    /**
     * Atomically revise an existing consumption:
     * 1. Reverts previous FIFO batch deductions and restores batch quantities.
     * 2. Posts offsetting adjustment_in stock ledger movements.
     * 3. Re-applies FIFO batch consumption with the new requested quantities.
     * 4. Updates consumption header with new cost, timestamp, and audit trail.
     *
     * @param Consumption $existing
     * @param array<int, array{product_id: int, quantity_used: float|int, notes?: string|null}> $newItems
     * @param int $userId
     * @param string|null $notes
     * @return Consumption
     */
    public function reviseConsumption(Consumption $existing, array $newItems, int $userId, ?string $notes = null): Consumption
    {
        return DB::transaction(function () use ($existing, $newItems, $userId, $notes) {
            $consumption = Consumption::where('id', $existing->id)->lockForUpdate()->firstOrFail();
            $date = $consumption->consumption_date->format('Y-m-d');

            // 1. Load and lock all existing consumption items
            $oldItems = ConsumptionItem::where('consumption_id', $consumption->id)
                ->lockForUpdate()
                ->get();

            // 2. Revert previous FIFO deductions for each item:
            // Restore batch remaining_quantity and post adjustment_in to stock ledger
            foreach ($oldItems as $oldItem) {
                $product = Product::lockForUpdate()->findOrFail($oldItem->product_id);
                $restoredQty = (float) $oldItem->quantity_used;

                if ($oldItem->stock_batch_id) {
                    $batch = StockBatch::where('id', $oldItem->stock_batch_id)->lockForUpdate()->first();
                    if ($batch) {
                        $batch->remaining_quantity = round((float) $batch->remaining_quantity + $restoredQty, 4);
                        if ($batch->remaining_quantity > 0) {
                            $batch->is_exhausted = false;
                        }
                        $batch->save();
                    }
                }

                // Credit back product current_stock and record adjustment_in
                $this->stockLedgerService->recordMovement(
                    product: $product,
                    movementType: 'adjustment_in',
                    quantityIn: $restoredQty,
                    quantityOut: 0,
                    unitCost: (float) $oldItem->unit_cost,
                    transactionDate: $date,
                    referenceType: Consumption::class,
                    referenceId: $consumption->id,
                    userId: $userId,
                    notes: "Stock restored from revision of Consumption #{$consumption->id}",
                    allowNegative: false,
                    stockBatchId: $oldItem->stock_batch_id
                );
            }

            // Remove previous consumption item rows
            ConsumptionItem::where('consumption_id', $consumption->id)->delete();

            // 3. Pre-validate that on-hand stock is sufficient for the new items
            foreach ($newItems as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $qty = (float) $item['quantity_used'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantity used for '{$product->name}' must be greater than zero.",
                    ]);
                }

                if ((float) $product->current_stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for '{$product->name}'. Available: {$product->current_stock} {$product->base_unit}, requested: {$qty} {$product->base_unit}.",
                    ]);
                }
            }

            // 4. Apply new FIFO batch consumption
            $totalCost = 0.00;

            foreach ($newItems as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                $needed = (float) $item['quantity_used'];

                $batches = StockBatch::where('product_id', $product->id)
                    ->where('is_exhausted', false)
                    ->where('remaining_quantity', '>', 0)
                    ->orderBy('received_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($batches->isEmpty() && (float) $product->current_stock > 0) {
                    $fallbackBatch = $this->stockLedgerService->createBatch(
                        product: $product,
                        quantity: (float) $product->current_stock,
                        unitCost: (float) $product->cost_price,
                        receivedDate: $date,
                        batchNo: 'OPENING-AUTO-' . $product->id
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

                    $batch->remaining_quantity = round($rem - $take, 4);
                    if ($batch->remaining_quantity <= 0) {
                        $batch->remaining_quantity = 0.0000;
                        $batch->is_exhausted = true;
                    }
                    $batch->save();

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
                        notes: "FIFO deduction from Batch {$batch->batch_no} (revised)",
                        allowNegative: false,
                        stockBatchId: $batch->id
                    );

                    ConsumptionItem::create([
                        'consumption_id' => $consumption->id,
                        'product_id' => $product->id,
                        'stock_batch_id' => $batch->id,
                        'quantity_used' => $take,
                        'unit_cost' => $sliceUnitCost,
                        'total_cost' => $sliceTotalCost,
                        'notes' => $item['notes'] ?? "FIFO Batch: {$batch->batch_no} (revised)",
                    ]);

                    $totalCost += $sliceTotalCost;
                    $needed = round($needed - $take, 4);
                }

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
                        notes: 'FIFO usage remaining balance (revised)',
                        allowNegative: false
                    );

                    ConsumptionItem::create([
                        'consumption_id' => $consumption->id,
                        'product_id' => $product->id,
                        'quantity_used' => $needed,
                        'unit_cost' => $unitCost,
                        'total_cost' => $sliceTotalCost,
                        'notes' => 'FIFO unbatched balance (revised)',
                    ]);

                    $totalCost += $sliceTotalCost;
                }
            }

            // 5. Update consumption record metadata
            $normalizedItems = collect($newItems)
                ->map(fn ($it) => [
                    'product_id' => (int) $it['product_id'],
                    'quantity_used' => round((float) $it['quantity_used'], 4),
                ])
                ->sortBy('product_id')
                ->values()
                ->toArray();
            $payloadHash = md5(json_encode($normalizedItems));

            $consumption->total_cost = round($totalCost, 2);
            $consumption->status = 'revised';
            $consumption->revised_by_id = $userId;
            $consumption->revised_at = now();
            $consumption->payload_hash = $payloadHash;
            if ($notes !== null) {
                $consumption->notes = $notes;
            }
            $consumption->save();

            return $consumption;
        });
    }
}

