<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wastage;
use App\Services\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $product = Product::findOrFail($validated['product_id']);
        $quantity = (float)$validated['quantity'];
        $unitCost = (float)$product->cost_price;
        $totalCost = round($quantity * $unitCost, 2);

        $wastage = Wastage::create([
            'product_id' => $product->id,
            'wastage_date' => $validated['wastage_date'],
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id() ?: 1,
        ]);

        $this->stockLedgerService->recordMovement(
            product: $product,
            movementType: 'wastage',
            quantityIn: 0,
            quantityOut: $quantity,
            unitCost: $unitCost,
            transactionDate: $validated['wastage_date'],
            referenceType: Wastage::class,
            referenceId: $wastage->id,
            userId: auth()->id() ?: 1,
            notes: "Wastage: {$validated['reason']}"
        );

        return redirect()->back()->with('success', 'Wastage recorded and deducted from stock ledger.');
    }
}

