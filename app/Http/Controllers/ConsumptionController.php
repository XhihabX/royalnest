<?php

namespace App\Http\Controllers;

use App\Models\Consumption;
use App\Models\Product;
use App\Services\ConsumptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumptionController extends Controller
{
    public function __construct(
        protected ConsumptionService $consumptionService
    ) {}

    public function index(): Response
    {
        $consumptions = Consumption::with(['creator', 'items.product'])
            ->latest('consumption_date')
            ->latest('id')
            ->limit(50)
            ->get();

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Consumption/Index', [
            'consumptions' => $consumptions,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'consumption_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_used' => 'required|numeric|min:0.0001',
            'items.*.notes' => 'nullable|string',
        ]);

        $this->consumptionService->recordConsumption(
            date: $validated['consumption_date'],
            items: $validated['items'],
            userId: auth()->id() ?: 1,
            notes: $validated['notes'] ?? null
        );

        return redirect()->back()->with('success', 'Daily consumption recorded and deducted from perpetual stock.');
    }
}

