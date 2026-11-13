<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Employee;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SavedCostTemplate;
use App\Models\Supplier;
use App\Services\SmartCostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SmartCostController extends Controller
{
    public function __construct(
        protected SmartCostService $smartCostService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $date = $request->query('date', now()->toDateString());

        $templates = $this->smartCostService->getTemplates($search);

        $products = Product::where('is_active', true)
            ->with(['unitConversions', 'category'])
            ->orderBy('name')
            ->get();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)
            ->with([
                'products',
                'purchases' => fn ($q) => $q->where('payment_status', '!=', 'paid')->orderBy('purchase_date', 'asc'),
            ])
            ->orderBy('name')
            ->get();

        $employees = Employee::where('is_active', true)
            ->with(['salaryPayments', 'attendances', 'advances'])
            ->orderBy('name')
            ->get()
            ->map(function ($emp) {
                $monthlyAgreed = (float) $emp->agreed_salary;
                $currentMonth = now()->format('Y-m');
                $paidThisMonth = $emp->salaryPayments->where('month_year', $currentMonth)->sum('amount');
                $heldDeposit = 0.00;
                if ((float) $emp->held_salary_amount > 0) {
                    $heldDeposit = (float) $emp->held_salary_amount;
                } elseif ((int) $emp->held_salary_days > 0) {
                    $dailyRate = $emp->salary_type === 'daily' ? $monthlyAgreed : round($monthlyAgreed / 30, 2);
                    $heldDeposit = round($dailyRate * (int) $emp->held_salary_days, 2);
                }
                $accrued = $monthlyAgreed;
                if ($emp->salary_type === 'daily') {
                    $present = $emp->attendances->where('status', 'present')->where('attendance_date', '>=', now()->startOfMonth()->toDateString())->count();
                    $half = $emp->attendances->where('status', 'half_day')->where('attendance_date', '>=', now()->startOfMonth()->toDateString())->count();
                    $accrued = round(($present + 0.5 * $half) * $monthlyAgreed, 2);
                }
                $unpaidPayable = max(0, round($accrued - $paidThisMonth - $heldDeposit, 2));

                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'employee_id' => $emp->employee_id,
                    'role' => $emp->role,
                    'department' => $emp->department,
                    'salary_type' => $emp->salary_type,
                    'agreed_salary' => $emp->agreed_salary,
                    'unpaid_payable' => $unpaidPayable,
                    'outstanding_advance' => round($emp->advances->sum('remaining_balance'), 2),
                ];
            });

        $accounts = FinancialAccount::where('is_active', true)->get();

        // Recent costs recorded for the selected date
        $recentPurchases = Purchase::whereDate('purchase_date', $date)
            ->with(['supplier', 'items.product'])
            ->latest('id')
            ->limit(10)
            ->get();

        $recentExpenses = OperatingExpense::whereDate('expense_date', $date)
            ->with('financialAccount')
            ->latest('id')
            ->limit(10)
            ->get();

        return Inertia::render('Costs/Index', [
            'templates' => $templates,
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'accounts' => $accounts,
            'recentPurchases' => $recentPurchases,
            'recentExpenses' => $recentExpenses,
            'selectedDate' => $date,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_type' => 'required|in:purchase,consumption,expense,salary_payment,salary_advance,supplier_payment,other',
            'transaction_date' => 'required|date',
            'name' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'employee_id' => 'nullable|exists:employees,id',
            'expense_category' => 'nullable|string|max:50',
            'quantity' => 'nullable|numeric|min:0.0001',
            'unit' => 'nullable|string|max:20',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_account_id' => 'nullable|exists:financial_accounts,id',
            'is_purchase_and_consume' => 'nullable|boolean',
            'recover_advances' => 'nullable|boolean',
            'salary_month' => 'nullable|string|max:7',
            'notes' => 'nullable|string|max:1000',
            'save_as_template' => 'nullable|boolean',
            'template_id' => 'nullable|exists:saved_cost_templates,id',
            'is_favorite' => 'nullable|boolean',
            'idempotency_key' => 'nullable|string|max:64',
            'allocations' => 'nullable|array',
            'allocations.*.purchase_id' => 'required_with:allocations|exists:purchases,id',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:0.01',
        ]);

        $userId = auth()->id() ?: 1;
        $idempotencyKey = $request->input('idempotency_key')
            ?: md5($userId.json_encode($request->only([
                'transaction_type',
                'transaction_date',
                'product_id',
                'supplier_id',
                'employee_id',
                'amount',
                'paid_amount',
                'quantity',
                'unit_price',
                'payment_account_id',
            ])));

        $lockKey = 'cost_entry_lock_'.$idempotencyKey;
        if (Cache::has($lockKey)) {
            return redirect()->back()->with('warning', 'This transaction was just submitted. Duplicate processing blocked.');
        }

        Cache::put($lockKey, true, now()->addSeconds(3));

        $result = $this->smartCostService->recordCost($validated, $userId);

        return redirect()->back()->with('success', $result['message']);
    }

    public function toggleFavorite(SavedCostTemplate $template): RedirectResponse
    {
        $template->is_favorite = ! $template->is_favorite;
        $template->save();

        return redirect()->back()->with('success', 'Favorite shortcut updated.');
    }

    public function destroyTemplate(SavedCostTemplate $template): RedirectResponse
    {
        $template->update(['is_active' => false]);

        return redirect()->back()->with('success', 'Cost template removed.');
    }
}
