<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Services\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $count = StockCount::create([
            'count_date' => $validated['count_date'],
            'status' => 'confirmed',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id() ?: 1,
            'confirmed_by' => auth()->id() ?: 1,
        ]);

        foreach ($validated['items'] as $itemData) {
            $product = Product::findOrFail($itemData['product_id']);
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

            // If there is a discrepancy, adjust stock in ledger
            if ($diffQty > 0) {
                // Stock In Adjustment
                $this->stockLedgerService->recordMovement(
                    product: $product,
                    movementType: 'adjustment_in',
                    quantityIn: $diffQty,
                    quantityOut: 0,
                    unitCost: $unitCost,
                    transactionDate: $validated['count_date'],
                    referenceType: StockCount::class,
                    referenceId: $count->id,
                    notes: "Stock count surplus adjustment: {$itemData['adjustment_reason']}"
                );
            } elseif ($diffQty < 0) {
                // Stock Out Adjustment
                $this->stockLedgerService->recordMovement(
                    product: $product,
                    movementType: 'adjustment_out',
                    quantityIn: 0,
                    quantityOut: abs($diffQty),
                    unitCost: $unitCost,
                    transactionDate: $validated['count_date'],
                    referenceType: StockCount::class,
                    referenceId: $count->id,
                    notes: "Stock count deficit adjustment: {$itemData['adjustment_reason']}"
                );
            }
        }

        return redirect()->back()->with('success', 'Physical stock count confirmed and ledger balances adjusted.');
    }
}

