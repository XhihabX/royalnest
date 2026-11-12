<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    /**
     * Section A: Supplier Directory (Master catalog of all vendors).
     */
    public function directory(Request $request): Response
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $status = $request->query('status');

        $result = $this->supplierService->getDirectorySuppliers($search, $category, $status);
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Suppliers/Directory', [
            'suppliers' => $result['suppliers'],
            'stats' => $result['stats'],
            'products' => $products,
            'filters' => [
                'search' => $search ?? '',
                'category' => $category ?? 'all',
                'status' => $status ?? 'all',
            ],
        ]);
    }

    /**
     * Section B: Active Supplier Management (Live vendors providing goods).
     */
    public function active(Request $request): Response
    {
        $search = $request->query('search');
        $result = $this->supplierService->getActiveSuppliers($search);
        $accounts = FinancialAccount::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Suppliers/Active', [
            'suppliers' => $result['suppliers'],
            'stats' => $result['stats'],
            'accounts' => $accounts,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Redirect default /suppliers to active management or directory.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('suppliers.active');
    }

    /**
     * Detailed Supplier Profile with statement, bills, vouchers, and product catalog.
     */
    public function profile(Request $request, Supplier $supplier): Response
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $statementData = $this->supplierService->getSupplierStatement($supplier->id, $startDate, $endDate);
        $accounts = FinancialAccount::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Suppliers/Profile', [
            'statement' => $statementData,
            'accounts' => $accounts,
            'products' => $products,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Add new supplier to the master directory.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'alternative_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:500',
            'business_location' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'status' => 'required|in:potential,active,inactive,archived',
            'preferred_payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'opening_balance' => 'nullable|numeric|min:0',
            'products' => 'nullable|array',
        ]);

        $this->supplierService->createDirectorySupplier($validated);

        return redirect()->back()->with('success', 'Supplier profile registered in Directory.');
    }

    /**
     * Update supplier details.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'alternative_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:500',
            'business_location' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|in:potential,active,inactive,archived',
            'preferred_payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $this->supplierService->updateDirectorySupplier($supplier, $validated);

        return redirect()->back()->with('success', 'Supplier details updated.');
    }

    /**
     * Change status: Activate, Deactivate, Archive.
     */
    public function setStatus(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:potential,active,inactive,archived',
        ]);

        $this->supplierService->setStatus($supplier, $validated['status']);

        return redirect()->back()->with('success', "Supplier status updated to {$validated['status']}.");
    }

    /**
     * Add a product to supplier's supply catalog.
     */
    public function addProduct(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'product_name' => 'required|string|max:150',
            'product_category' => 'nullable|string|max:100',
            'purchase_unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'min_order_quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->supplierService->addSupplierProduct($supplier, $validated);

        return redirect()->back()->with('success', 'Product added to supplier catalog.');
    }

    /**
     * Delete product from supplier catalog.
     */
    public function deleteProduct(SupplierProduct $supplierProduct): RedirectResponse
    {
        $this->supplierService->deleteSupplierProduct($supplierProduct->id);

        return redirect()->back()->with('success', 'Product removed from supplier catalog.');
    }

    /**
     * Record payment voucher against supplier due balance.
     */
    public function payDue(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_account_id' => 'nullable|exists:financial_accounts,id',
            'payment_method' => 'required|string|in:cash,bank,mfs,cheque,other',
            'notes' => 'nullable|string|max:500',
        ]);

        $userId = auth()->id() ?? 1;

        $payment = $this->supplierService->paySupplierDue(
            supplierId: $supplier->id,
            amount: (float) $validated['amount'],
            paymentDate: $validated['payment_date'],
            paymentAccountId: ! empty($validated['payment_account_id']) ? (int) $validated['payment_account_id'] : null,
            paymentMethod: $validated['payment_method'],
            notes: $validated['notes'] ?? null,
            userId: $userId
        );

        return redirect()->back()->with('success', "Payment voucher #{$payment->voucher_no} recorded successfully. Due updated.");
    }

    /**
     * Record advance payment to a supplier.
     */
    public function advance(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'advance_date' => 'required|date',
            'payment_account_id' => 'nullable|exists:financial_accounts,id',
            'payment_method' => 'required|string|in:cash,bank,mfs,cheque,other',
            'reason' => 'nullable|string|max:255',
        ]);

        $userId = auth()->id() ?? 1;

        $advance = $this->supplierService->recordAdvance(
            supplierId: $supplier->id,
            amount: (float) $validated['amount'],
            advanceDate: $validated['advance_date'],
            paymentAccountId: ! empty($validated['payment_account_id']) ? (int) $validated['payment_account_id'] : null,
            paymentMethod: $validated['payment_method'],
            reason: $validated['reason'] ?? null,
            userId: $userId
        );

        return redirect()->back()->with('success', "Supplier advance voucher #{$advance->voucher_no} recorded.");
    }
}
