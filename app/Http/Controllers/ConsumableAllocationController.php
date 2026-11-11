<?php

namespace App\Http\Controllers;

use App\Models\ConsumableAllocation;
use App\Models\Product;
use App\Services\ConsumableAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumableAllocationController extends Controller
{
    public function __construct(
        protected ConsumableAllocationService $consumableAllocationService
    ) {}

    public function index(): Response
    {
        $allocations = ConsumableAllocation::with('product')
            ->latest('start_date')
            ->get();

        $consumables = Product::where('is_consumable', true)
            ->where('is_active', true)
            ->get();

        return Inertia::render('Consumables/Index', [
            'allocations' => $allocations,
            'consumables' => $consumables,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'start_date' => 'required|date',
            'duration_days' => 'required|integer|min:1',
            'total_cost' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        $this->consumableAllocationService->createAllocation(
            productId: $validated['product_id'],
            purchaseId: null,
            startDate: $validated['start_date'],
            durationDays: $validated['duration_days'],
            totalCost: $validated['total_cost'],
            notes: $validated['notes'] ?? null
        );

        return redirect()->back()->with('success', 'Consumable cost amortization schedule created.');
    }
}

