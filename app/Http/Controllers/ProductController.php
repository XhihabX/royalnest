<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Supplier;
use App\Services\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function index(): Response
    {
        $products = Product::with(['category', 'supplier', 'unitConversions', 'activeBatches'])
            ->orderBy('name')
            ->get();

        $categories = Category::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'product_type' => 'required|in:raw_material,consumable,other_stock',
            'base_unit' => 'required|string|max:20',
            'purchase_unit' => 'required|string|max:30',
            'conversion_multiplier' => 'required|numeric|min:0.0001',
            'min_reorder_level' => 'nullable|numeric|min:0',
            'is_consumable' => 'boolean',
            'default_allocation_days' => 'nullable|integer|min:1',
            'opening_stock' => 'nullable|numeric|min:0',
            'opening_cost_price' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'category_id' => $validated['category_id'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'product_type' => $validated['product_type'],
            'base_unit' => $validated['base_unit'],
            'purchase_unit' => $validated['purchase_unit'],
            'current_stock' => 0.0,
            'cost_price' => $validated['opening_cost_price'] ?? 0.0,
            'min_reorder_level' => $validated['min_reorder_level'] ?? 0.0,
            'is_consumable' => ! empty($validated['is_consumable']),
            'default_allocation_days' => $validated['default_allocation_days'] ?? null,
            'is_active' => true,
        ]);

        // Create unit conversion
        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => $validated['purchase_unit'],
            'multiplier_to_base' => $validated['conversion_multiplier'],
        ]);

        // Record opening stock if provided
        $openingStock = (float) ($validated['opening_stock'] ?? 0);
        $openingCost = (float) ($validated['opening_cost_price'] ?? 0);
        if ($openingStock > 0) {
            $this->stockLedgerService->recordMovement(
                product: $product,
                movementType: 'opening_stock',
                quantityIn: $openingStock,
                quantityOut: 0,
                unitCost: $openingCost,
                transactionDate: now()->toDateString(),
                userId: auth()->id() ?: 1,
                notes: 'Initial opening stock setup'
            );
        }

        return redirect()->back()->with('success', "Product '{$product->name}' created successfully.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'product_type' => 'required|in:raw_material,consumable,packaging,other_stock',
            'base_unit' => 'required|string|max:20',
            'purchase_unit' => 'required|string|max:30',
            'conversion_multiplier' => 'nullable|numeric|min:0.0001',
            'min_reorder_level' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $before = $product->only(['name', 'category_id', 'product_type', 'purchase_unit', 'min_reorder_level', 'is_active']);

        $product->update([
            'category_id' => $validated['category_id'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'product_type' => $validated['product_type'],
            'base_unit' => $validated['base_unit'],
            'purchase_unit' => $validated['purchase_unit'],
            'min_reorder_level' => $validated['min_reorder_level'] ?? 0.0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['conversion_multiplier'])) {
            ProductUnitConversion::updateOrCreate(
                ['product_id' => $product->id, 'purchase_unit' => $validated['purchase_unit']],
                ['multiplier_to_base' => $validated['conversion_multiplier']]
            );
        }

        \App\Services\AuditLogger::log(
            action: 'updated',
            description: "Updated product '{$product->name}'",
            entity: $product,
            beforePayload: $before,
            afterPayload: $product->only(['name', 'category_id', 'product_type', 'purchase_unit', 'min_reorder_level', 'is_active']),
            module: 'inventory'
        );

        return redirect()->back()->with('success', "Product '{$product->name}' updated.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        \App\Services\AuditLogger::log(
            action: 'deleted',
            description: "Soft deleted product '{$name}' to Recycle Bin",
            entity: $product,
            module: 'recycle_bin'
        );

        return redirect()->back()->with('success', "Product '{$name}' moved to Recycle Bin.");
    }
}
