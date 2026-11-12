<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Wastage;
use App\Services\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WastageController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function index(): Response
    {
        $wastages = Wastage::with(['product', 'creator'])
            ->orderBy('wastage_date', 'desc')
            ->limit(100)
            ->get();

        $products = Product::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Wastage/Index', [
            'wastages' => $wastages,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'wastage_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'required|in:spoiled,expired,burnt,accidental_loss,other',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);
            $quantity = (float)$validated['quantity'];

            // 1. Negative stock prevention
            if ((float)$product->current_stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock for '{$product->name}'. Available: {$product->current_stock} {$product->base_unit}, requested: {$quantity} {$product->base_unit}."
                ]);
            }

            // 2. Fetch active batches ordered chronologically (FIFO)
            $batches = StockBatch::where('product_id', $product->id)
                ->where('is_exhausted', false)
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Fallback batch if needed
            if ($batches->isEmpty() && (float)$product->current_stock > 0) {
                $fallbackBatch = $this->stockLedgerService->createBatch(
                    product: $product,
                    quantity: (float)$product->current_stock,
                    unitCost: (float)$product->cost_price,
                    receivedDate: $validated['wastage_date'],
                    batchNo: 'OPENING-AUTO-' . $product->id
                );
                $batches = collect([$fallbackBatch]);
            }

            // 3. Create initial Wastage record
            $wastage = Wastage::create([
                'product_id' => $product->id,
                'wastage_date' => $validated['wastage_date'],
                'quantity' => $quantity,
                'unit_cost' => 0.00,
                'total_cost' => 0.00,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            $needed = $quantity;
            $totalWastageCost = 0.00;

            // 4. FIFO batch deduction traversal
            foreach ($batches as $batch) {
                if ($needed <= 0) break;

                $rem = (float)$batch->remaining_quantity;
                $take = min($rem, $needed);
                $sliceUnitCost = (float)$batch->unit_cost;
                $sliceCost = round($take * $sliceUnitCost, 2);

                $batch->remaining_quantity = round($rem - $take, 4);
                if ($batch->remaining_quantity <= 0) {
                    $batch->remaining_quantity = 0.0000;
                    $batch->is_exhausted = true;
                }
                $batch->save();

                $this->stockLedgerService->recordMovement(
                    product: $product,
                    movementType: 'wastage',
                    quantityIn: 0,
                    quantityOut: $take,
                    unitCost: $sliceUnitCost,
                    transactionDate: $validated['wastage_date'],
                    referenceType: Wastage::class,
                    referenceId: $wastage->id,
                    userId: auth()->id() ?: 1,
                    notes: "FIFO wastage from Batch {$batch->batch_no} ({$validated['reason']})",
                    allowNegative: false,
                    stockBatchId: $batch->id
                );

                $totalWastageCost += $sliceCost;
                $needed = round($needed - $take, 4);
            }

            // Remainder unbatched balance if any
            if ($needed > 0) {
                $unitCost = (float)$product->cost_price;
                $sliceCost = round($needed * $unitCost, 2);

                $this->stockLedgerService->recordMovement(
                    product: $product,
                    movementType: 'wastage',
                    quantityIn: 0,
                    quantityOut: $needed,
                    unitCost: $unitCost,
                    transactionDate: $validated['wastage_date'],
                    referenceType: Wastage::class,
                    referenceId: $wastage->id,
                    userId: auth()->id() ?: 1,
                    notes: "FIFO wastage unbatched remainder ({$validated['reason']})",
                    allowNegative: false
                );

                $totalWastageCost += $sliceCost;
            }

            $avgUnitCost = $quantity > 0 ? round($totalWastageCost / $quantity, 4) : 0.00;
            $wastage->unit_cost = $avgUnitCost;
            $wastage->total_cost = round($totalWastageCost, 2);
            $wastage->save();
        });

        return redirect()->back()->with('success', 'Wastage recorded with accurate FIFO batch costing and deducted from stock ledger.');
    }
}

