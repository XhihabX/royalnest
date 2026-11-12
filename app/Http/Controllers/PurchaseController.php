<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    public function index(): Response
    {
        $purchases = Purchase::with(['supplier', 'creator', 'items.product'])
            ->latest('purchase_date')
            ->latest('id')
            ->limit(100)
            ->get();

        $products = Product::where('is_active', true)
            ->with('unitConversions')
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('is_active', true)->get();
        $accounts = FinancialAccount::where('is_active', true)->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'products' => $products,
            'suppliers' => $suppliers,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'invoice_no' => 'nullable|string|max:50',
            'purchase_date' => 'required|date',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_account_id' => 'nullable|exists:financial_accounts,id',
            'is_purchase_and_consume' => 'boolean',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.purchase_quantity' => 'required|numeric|min:0.0001',
            'items.*.purchase_unit' => 'required|string',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $purchase = $this->purchaseService->confirmPurchase(
            purchaseData: [
                'supplier_id' => $validated['supplier_id'] ?? null,
                'invoice_no' => $validated['invoice_no'] ?? null,
                'purchase_date' => $validated['purchase_date'],
                'paid_amount' => $validated['paid_amount'] ?? 0.0,
                'payment_account_id' => $validated['payment_account_id'] ?? null,
                'is_purchase_and_consume' => ! empty($validated['is_purchase_and_consume']),
                'notes' => $validated['notes'] ?? null,
            ],
            items: $validated['items'],
            userId: auth()->id() ?: 1
        );

        $msg = $purchase->is_purchase_and_consume
            ? 'Purchase & Consume completed successfully. Stock received and consumed immediately with no duplicate expense.'
            : 'Purchase recorded and confirmed. Stock and moving average cost updated.';

        return redirect()->back()->with('success', $msg);
    }
}
