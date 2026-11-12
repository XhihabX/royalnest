<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Services\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function index(): Response
    {
        $counts = StockCount::with(['creator', 'confirmer', 'items.product'])
            ->latest('count_date')
            ->get();

        $products = Product::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('StockCounts/Index', [
            'counts' => $counts,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'count_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.counted_quantity' => 'required|numeric|min:0',
            'items.*.adjustment_reason' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $count = StockCount::create([
                'count_date' => $validated['count_date'],
                'status' => 'confirmed',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
                'confirmed_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                $sysQty = (float)$product->current_stock;
                $countedQty = (float)$itemData['counted_quantity'];
                $diffQty = round($countedQty - $sysQty, 4);
                $unitCost = (float)$product->cost_price;
                $diffValue = round($diffQty * $unitCost, 2);

                StockCountItem::create([
                    'stock_count_id' => $count->id,
                    'product_id' => $product->id,
                    'system_quantity' => $sysQty,
                    'counted_quantity' => $countedQty,
                    'difference_quantity' => $diffQty,
                    'unit_cost' => $unitCost,
                    'difference_value' => $diffValue,
                    'adjustment_reason' => $itemData['adjustment_reason'] ?? null,
                ]);

                // Surplus: create dedicated StockBatch and ledger movement
                if ($diffQty > 0) {
                    $batchNo = 'COUNT-ADJ-' . date('Ymd', strtotime($validated['count_date'])) . '-' . $product->id . '-' . strtoupper(substr(uniqid(), -4));
                    $batch = $this->stockLedgerService->createBatch(
                        product: $product,
                        quantity: $diffQty,
                        unitCost: $unitCost,
                        receivedDate: $validated['count_date'],
                        batchNo: $batchNo
                    );

                    $this->stockLedgerService->recordMovement(
                        product: $product,
                        movementType: 'adjustment_in',
                        quantityIn: $diffQty,
                        quantityOut: 0,
                        unitCost: $unitCost,
                        transactionDate: $validated['count_date'],
                        referenceType: StockCount::class,
                        referenceId: $count->id,
                        notes: "Stock count surplus ({$batchNo}): " . ($itemData['adjustment_reason'] ?? 'Count audit surplus'),
                        allowNegative: false,
                        stockBatchId: $batch->id
                    );
                } elseif ($diffQty < 0) {
                    // Deficit: deplete active FIFO batches chronologically
                    $needed = abs($diffQty);

                    $batches = StockBatch::where('product_id', $product->id)
                        ->where('is_exhausted', false)
                        ->where('remaining_quantity', '>', 0)
                        ->orderBy('received_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get();

                    foreach ($batches as $batch) {
                        if ($needed <= 0) break;

                        $rem = (float)$batch->remaining_quantity;
                        $take = min($rem, $needed);
                        $sliceUnitCost = (float)$batch->unit_cost;

                        $batch->remaining_quantity = round($rem - $take, 4);
                        if ($batch->remaining_quantity <= 0) {
                            $batch->remaining_quantity = 0.0000;
                            $batch->is_exhausted = true;
                        }
                        $batch->save();

                        $this->stockLedgerService->recordMovement(
                            product: $product,
                            movementType: 'adjustment_out',
                            quantityIn: 0,
                            quantityOut: $take,
                            unitCost: $sliceUnitCost,
                            transactionDate: $validated['count_date'],
                            referenceType: StockCount::class,
                            referenceId: $count->id,
                            notes: "Stock count deficit from Batch {$batch->batch_no}: " . ($itemData['adjustment_reason'] ?? 'Count audit deficit'),
                            allowNegative: false,
                            stockBatchId: $batch->id
                        );

                        $needed = round($needed - $take, 4);
                    }

                    if ($needed > 0) {
                        $this->stockLedgerService->recordMovement(
                            product: $product,
                            movementType: 'adjustment_out',
                            quantityIn: 0,
                            quantityOut: $needed,
                            unitCost: $unitCost,
                            transactionDate: $validated['count_date'],
                            referenceType: StockCount::class,
                            referenceId: $count->id,
                            notes: "Stock count deficit unbatched remainder: " . ($itemData['adjustment_reason'] ?? 'Count audit deficit'),
                            allowNegative: false
                        );
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Physical stock count confirmed and FIFO batches synchronized with ledger.');
    }
}

