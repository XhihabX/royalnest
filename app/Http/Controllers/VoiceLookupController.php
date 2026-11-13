<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceLookupController extends Controller
{
    /**
     * Provide fresh database-backed search results for voice entity extraction.
     */
    public function lookup(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all');
        $query = trim((string) $request->query('q', ''));

        $results = [];

        switch ($type) {
            case 'products':
            case 'purchase_entities':
            case 'packaging_entities':
                $q = Product::query()->where('is_active', true);
                if ($type === 'packaging_entities') {
                    $q->where('product_type', 'packaging');
                }
                if ($query !== '') {
                    $q->where(function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('sku', 'like', "%{$query}%");
                    });
                }
                $results['products'] = $q->limit(15)->get(['id', 'name', 'product_type', 'purchase_unit', 'cost_price']);
                if ($type === 'purchase_entities') {
                    $suppliers = Supplier::where('status', 'active');
                    if ($query !== '') {
                        $suppliers->where(function ($sub) use ($query) {
                            $sub->where('company_name', 'like', "%{$query}%")
                                ->orWhere('contact_person', 'like', "%{$query}%");
                        });
                    }
                    $results['suppliers'] = $suppliers->limit(15)->get(['id', 'company_name', 'contact_person', 'phone']);
                }
                break;

            case 'suppliers':
            case 'supplier_bills':
                $suppliers = Supplier::query();
                if ($query !== '') {
                    $suppliers->where(function ($sub) use ($query) {
                        $sub->where('company_name', 'like', "%{$query}%")
                            ->orWhere('contact_person', 'like', "%{$query}%");
                    });
                }
                $results['suppliers'] = $suppliers->limit(15)->get(['id', 'company_name', 'contact_person', 'current_balance', 'status']);
                break;

            case 'employees':
                $emp = Employee::where('status', 'active');
                if ($query !== '') {
                    $emp->where(function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('employee_id', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%");
                    });
                }
                $results['employees'] = $emp->limit(15)->get(['id', 'employee_id', 'name', 'role', 'department', 'agreed_salary', 'salary_type', 'assigned_shift']);
                break;

            case 'cost_entities':
            case 'all':
            default:
                $results['products'] = Product::where('is_active', true)
                    ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
                    ->limit(10)
                    ->get(['id', 'name', 'cost_price', 'purchase_unit']);

                $results['suppliers'] = Supplier::where('status', 'active')
                    ->when($query !== '', fn ($q) => $q->where('company_name', 'like', "%{$query}%"))
                    ->limit(10)
                    ->get(['id', 'company_name', 'current_balance']);

                $results['employees'] = Employee::where('status', 'active')
                    ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
                    ->limit(10)
                    ->get(['id', 'name', 'role', 'department']);

                $results['accounts'] = FinancialAccount::where('is_active', true)
                    ->get(['id', 'name', 'type', 'current_balance']);
                break;
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'type' => $type,
            'data' => $results,
        ]);
    }
}
