<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecycleBinController extends Controller
{
    /**
     * Display a listing of soft-deleted records across recoverable entities.
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'products');

        $products = Product::onlyTrashed()->with('category')->latest('deleted_at')->paginate(20, ['*'], 'products_page');
        $suppliers = Supplier::onlyTrashed()->latest('deleted_at')->paginate(20, ['*'], 'suppliers_page');
        $employees = Employee::onlyTrashed()->latest('deleted_at')->paginate(20, ['*'], 'employees_page');
        $categories = Category::onlyTrashed()->latest('deleted_at')->paginate(20, ['*'], 'categories_page');

        return Inertia::render('RecycleBin/Index', [
            'activeTab' => $tab,
            'products' => $products,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'categories' => $categories,
            'counts' => [
                'products' => Product::onlyTrashed()->count(),
                'suppliers' => Supplier::onlyTrashed()->count(),
                'employees' => Employee::onlyTrashed()->count(),
                'categories' => Category::onlyTrashed()->count(),
            ],
        ]);
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:product,supplier,employee,category',
            'id' => 'required|integer',
        ]);

        $type = $validated['type'];
        $id = $validated['id'];

        switch ($type) {
            case 'product':
                $record = Product::onlyTrashed()->findOrFail($id);
                // Check if an active product already has the same name
                $conflict = Product::where('name', $record->name)->where('id', '!=', $record->id)->first();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'restore' => "Cannot restore: An active product with name '{$record->name}' already exists. Rename the active product first.",
                    ]);
                }
                $record->restore();
                break;

            case 'supplier':
                $record = Supplier::onlyTrashed()->findOrFail($id);
                $conflict = Supplier::where('phone', $record->phone)->where('id', '!=', $record->id)->first();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'restore' => "Cannot restore: An active supplier with phone '{$record->phone}' already exists.",
                    ]);
                }
                $record->restore();
                break;

            case 'employee':
                $record = Employee::onlyTrashed()->findOrFail($id);
                $conflict = Employee::where('employee_id', $record->employee_id)->where('id', '!=', $record->id)->first();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'restore' => "Cannot restore: An active employee with ID '{$record->employee_id}' already exists.",
                    ]);
                }
                $record->restore();
                break;

            case 'category':
                $record = Category::onlyTrashed()->findOrFail($id);
                $conflict = Category::where('name', $record->name)->where('id', '!=', $record->id)->first();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'restore' => "Cannot restore: An active category with name '{$record->name}' already exists.",
                    ]);
                }
                $record->restore();
                break;
        }

        AuditLogger::log(
            action: 'restored',
            description: "Restored {$type} #{$id} from Recycle Bin",
            entity: $record,
            module: 'recycle_bin'
        );

        return back()->with('success', ucfirst($type) . ' successfully restored.');
    }

    /**
     * Permanently purge an eligible non-financial record (Restricted to Super Admin).
     */
    public function purge(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403, 'Only the Super Admin can permanently purge records from the Recycle Bin.');
        }

        $validated = $request->validate([
            'type' => 'required|string|in:product,supplier,employee,category',
            'id' => 'required|integer',
        ]);

        $type = $validated['type'];
        $id = $validated['id'];

        switch ($type) {
            case 'product':
                $record = Product::onlyTrashed()->findOrFail($id);
                // Dependency check: purchases, batches, stock ledgers, consumptions
                $hasPurchases = DB::table('purchase_items')->where('product_id', $id)->exists();
                $hasBatches = DB::table('stock_batches')->where('product_id', $id)->exists();
                $hasLedger = DB::table('stock_ledger')->where('product_id', $id)->exists();
                $hasConsumption = DB::table('consumption_items')->where('product_id', $id)->exists();

                if ($hasPurchases || $hasBatches || $hasLedger || $hasConsumption) {
                    throw ValidationException::withMessages([
                        'purge' => "Cannot permanently delete product '{$record->name}' because historical inventory or purchase transactions depend on it. It must remain archived in the Recycle Bin.",
                    ]);
                }

                $record->forceDelete();
                break;

            case 'supplier':
                $record = Supplier::onlyTrashed()->findOrFail($id);
                $hasPurchases = DB::table('purchases')->where('supplier_id', $id)->exists();
                $hasPayments = DB::table('supplier_payments')->where('supplier_id', $id)->exists();

                if ($hasPurchases || $hasPayments) {
                    throw ValidationException::withMessages([
                        'purge' => "Cannot permanently delete supplier '{$record->company_name}' because historical purchase invoices or payment vouchers depend on it.",
                    ]);
                }

                $record->forceDelete();
                break;

            case 'employee':
                $record = Employee::onlyTrashed()->findOrFail($id);
                $hasAttendance = DB::table('employee_attendances')->where('employee_id', $id)->exists();
                $hasSalary = DB::table('salary_payments')->where('employee_id', $id)->exists();
                $hasAdvances = DB::table('employee_advances')->where('employee_id', $id)->exists();

                if ($hasAttendance || $hasSalary || $hasAdvances) {
                    throw ValidationException::withMessages([
                        'purge' => "Cannot permanently delete staff member '{$record->name}' because historical attendance, advance, or salary records depend on it.",
                    ]);
                }

                $record->forceDelete();
                break;

            case 'category':
                $record = Category::onlyTrashed()->findOrFail($id);
                $hasProducts = DB::table('products')->where('category_id', $id)->exists();

                if ($hasProducts) {
                    throw ValidationException::withMessages([
                        'purge' => "Cannot permanently delete category '{$record->name}' because products are still assigned to it.",
                    ]);
                }

                $record->forceDelete();
                break;
        }

        AuditLogger::log(
            action: 'purged',
            description: "Permanently purged {$type} #{$id} from Recycle Bin",
            entity: null,
            module: 'recycle_bin'
        );

        return back()->with('success', ucfirst($type) . ' permanently deleted.');
    }
}
