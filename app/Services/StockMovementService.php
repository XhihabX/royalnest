<?php

namespace App\Services;

use App\Models\StockBatch;
use App\Models\StockItem;
use App\Models\StockLocation;
use App\Models\StockLocationQuantity;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockMovementService
{
    public function __construct(
        protected UnitConversionService $unitConverter
    ) {}

    /**
     * Record an atomic stock movement with moving weighted-average cost update.
     */
    public function recordMovement(
        int $branchId,
        int $locationId,
        int $stockItemId,
        string $movementType,
        float|string $quantity,
        int $unitId,
        ?float $unitCost = null,
        ?string $referenceModule = null,
        ?int $referenceId = null,
        ?string $reasonCode = null,
        ?string $notes = null,
        ?int $userId = null
    ): StockMovement {
        return DB::transaction(function () use (
            $branchId,
            $locationId,
            $stockItemId,
            $movementType,
            $quantity,
            $unitId,
            $unitCost,
            $referenceModule,
            $referenceId,
            $reasonCode,
            $notes,
            $userId
        ) {
            $stockItem = StockItem::where('id', $stockItemId)
                ->lockForUpdate()
                ->firstOrFail();

            $location = StockLocation::where('id', $locationId)
                ->where('branch_id', $branchId)
                ->firstOrFail();

            // Convert quantity to stock item's stock_unit
            $qtyInStockUnit = $this->unitConverter->convert(
                (float) $quantity,
                $unitId,
                $stockItem->stock_unit_id
            );

            // Determine if movement is an addition or subtraction
            $isAddition = in_array($movementType, ['goods_received', 'transfer_in'], true);
            $isDeduction = in_array($movementType, ['recipe_consumption', 'direct_issue', 'wastage', 'staff_meal', 'transfer_out'], true);

            $signedQuantity = $isAddition ? abs($qtyInStockUnit) : ($isDeduction ? -abs($qtyInStockUnit) : (float) $qtyInStockUnit);

            $currentStock = (float) $stockItem->current_stock;
            $currentCost = (float) $stockItem->cost_price;
            $appliedUnitCost = $unitCost !== null ? (float) $unitCost : $currentCost;

            // 1. Moving weighted-average cost calculation on goods receipt
            if ($movementType === 'goods_received' && $isAddition) {
                $receivedQty = abs($qtyInStockUnit);
                $newTotalQty = $currentStock + $receivedQty;

                if ($newTotalQty > 0) {
                    $totalCurrentValue = $currentStock > 0 ? ($currentStock * $currentCost) : 0;
                    $receivedValue = $receivedQty * $appliedUnitCost;
                    $newWeightedAverageCost = round(($totalCurrentValue + $receivedValue) / $newTotalQty, 4);
                    $stockItem->cost_price = $newWeightedAverageCost;
                } else {
                    $stockItem->cost_price = $appliedUnitCost;
                }
            }

            // 2. Update overall stock item balance
            $stockItem->current_stock = $currentStock + $signedQuantity;
            $stockItem->save();

            // 3. Update location-specific quantity
            $locationQty = StockLocationQuantity::firstOrCreate(
                [
                    'stock_item_id' => $stockItem->id,
                    'stock_location_id' => $location->id,
                ],
                ['quantity' => 0.0000]
            );

            $locationQty->quantity = (float) $locationQty->quantity + $signedQuantity;
            $locationQty->save();

            // 4. Batch FEFO deduction if batch tracking enabled
            $batchId = null;
            if ($stockItem->track_batches && $isDeduction) {
                $batchId = $this->dispatchFefoBatch($stockItem->id, $location->id, abs($qtyInStockUnit));
            }

            // 5. Create immutable movement ledger entry
            $totalCost = round(abs($qtyInStockUnit) * $appliedUnitCost, 2);

            return StockMovement::create([
                'branch_id' => $branchId,
                'stock_location_id' => $location->id,
                'stock_item_id' => $stockItem->id,
                'batch_id' => $batchId,
                'movement_type' => $movementType,
                'quantity' => $signedQuantity,
                'unit_id' => $stockItem->stock_unit_id,
                'unit_cost' => $appliedUnitCost,
                'total_cost' => $totalCost,
                'reference_module' => $referenceModule,
                'reference_id' => $referenceId,
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'created_by' => $userId ?? auth()->id() ?? 1,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Deduct from the earliest expiring batch (FEFO).
     */
    protected function dispatchFefoBatch(int $stockItemId, int $locationId, float $deductQty): ?int
    {
        $batch = StockBatch::where('stock_item_id', $stockItemId)
            ->where('stock_location_id', $locationId)
            ->where('remaining_quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->first();

        if ($batch) {
            $deducted = min((float) $batch->remaining_quantity, $deductQty);
            $batch->remaining_quantity = (float) $batch->remaining_quantity - $deducted;
            $batch->save();
            return $batch->id;
        }

        return null;
    }
}
