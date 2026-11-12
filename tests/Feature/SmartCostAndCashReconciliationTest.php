<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyCashReconciliation;
use App\Models\DailySale;
use App\Models\Employee;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\SavedCostTemplate;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CashReconciliationService;
use App\Services\FinancialAccountService;
use App\Services\SmartCostService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartCostAndCashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected FinancialAccount $cashAccount;

    protected FinancialAccount $bkashAccount;

    protected Category $rawCategory;

    protected Supplier $supplier;

    protected Employee $employee;

    protected SmartCostService $smartCostService;

    protected CashReconciliationService $cashReconciliationService;

    protected StockLedgerService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'manager_'.uniqid().'@royalnest.com',
            'primary_role' => 'manager',
            'is_active' => true,
        ]);

        $this->smartCostService = app(SmartCostService::class);
        $this->cashReconciliationService = app(CashReconciliationService::class);
        $this->stockService = app(StockLedgerService::class);

        // Setup Financial Accounts
        $this->cashAccount = FinancialAccount::create([
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'current_balance' => 10000.00,
            'is_active' => true,
        ]);

        $this->bkashAccount = FinancialAccount::create([
            'name' => 'bKash Merchant',
            'type' => 'bkash',
            'current_balance' => 5000.00,
            'is_active' => true,
        ]);

        // Category
        $this->rawCategory = Category::create([
            'name' => 'Raw Materials',
            'slug' => 'raw-materials',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        // Supplier
        $this->supplier = Supplier::create([
            'name' => 'Karbari Fresh Vegetables',
            'company_name' => 'Karbari Traders',
            'phone' => '01711000111',
            'status' => 'active',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        // Employee
        $this->employee = Employee::create([
            'employee_id' => 'EMP-TEST-001',
            'name' => 'Rahim Mia',
            'phone' => '01811000222',
            'role' => 'Line Cook',
            'salary_type' => 'monthly',
            'agreed_salary' => 15000.00,
            'joining_date' => '2026-01-01',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function createProduct(string $name, float $cost = 50.00, float $stock = 0.00): Product
    {
        $product = Product::create([
            'category_id' => $this->rawCategory->id,
            'name' => $name,
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 0.00,
            'cost_price' => $cost,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'multiplier_to_base' => 1.0,
        ]);

        if ($stock > 0) {
            $batch = $this->stockService->createBatch($product, $stock, $cost, now()->toDateString());
            $this->stockService->recordMovement(
                product: $product,
                movementType: 'opening_stock',
                quantityIn: $stock,
                quantityOut: 0.0,
                unitCost: $cost,
                transactionDate: now()->toDateString(),
                referenceType: 'Initial_Stock',
                referenceId: 1,
                userId: $this->user->id,
                notes: 'Test initial stock',
                allowNegative: false,
                stockBatchId: $batch->id
            );
        }

        return $product->fresh();
    }

    public function test_user_can_access_daily_costs_page(): void
    {
        $response = $this->actingAs($this->user)->get('/daily-costs');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Costs/Index'));
    }

    public function test_user_can_access_cash_reconciliation_page(): void
    {
        $response = $this->actingAs($this->user)->get('/cash-reconciliation');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Cash/Reconciliation'));
    }

    public function test_inventory_purchase_cost_entry_creates_stock_payable_and_financial_records_atomically(): void
    {
        $cucumber = $this->createProduct('Cucumber (শসা)', 40.00, 0.00);

        $payload = [
            'transaction_type' => 'purchase',
            'transaction_date' => now()->toDateString(),
            'product_id' => $cucumber->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 10,
            'unit' => 'kg',
            'unit_price' => 45.00,
            'amount' => 450.00,
            'payment_account_id' => $this->cashAccount->id,
            'paid_amount' => 450.00, // Fully paid in cash
            'notes' => '10kg fresh cucumbers from daily cost entry',
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertRedirect();

        // Verify Product Stock & Moving Average Cost
        $cucumber->refresh();
        $this->assertEquals(10.0, (float) $cucumber->current_stock);
        $this->assertEquals(45.00, (float) $cucumber->cost_price);

        // Verify Supplier Due is 0 (fully paid)
        $this->supplier->refresh();
        $this->assertEquals(0.00, (float) $this->supplier->current_balance);

        // Verify Cash Account Balance (10000 - 450 = 9550)
        $this->cashAccount->refresh();
        $this->assertEquals(9550.00, (float) $this->cashAccount->current_balance);
    }

    public function test_inventory_purchase_with_partial_payment_sets_correct_supplier_due(): void
    {
        $tomato = $this->createProduct('Tomato (টমেটো)', 50.00, 0.00);

        $payload = [
            'transaction_type' => 'purchase',
            'transaction_date' => now()->toDateString(),
            'product_id' => $tomato->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 20,
            'unit' => 'kg',
            'unit_price' => 60.00,
            'amount' => 1200.00,
            'payment_account_id' => $this->cashAccount->id,
            'paid_amount' => 500.00, // Partial cash payment
            'notes' => 'Tomato purchase with partial cash payment',
        ];

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        $this->supplier->refresh();
        // 1200 total - 500 paid = 700 unpaid due balance
        $this->assertEquals(700.00, (float) $this->supplier->current_balance);

        // Cash account reduced by paid_amount (500)
        $this->cashAccount->refresh();
        $this->assertEquals(9500.00, (float) $this->cashAccount->current_balance);
    }

    public function test_raw_material_consumption_cost_entry_reduces_stock_via_fifo(): void
    {
        $onion = $this->createProduct('Onion (পেঁয়াজ)', 60.00, 25.00);

        $payload = [
            'transaction_type' => 'consumption',
            'transaction_date' => now()->toDateString(),
            'product_id' => $onion->id,
            'quantity' => 5,
            'unit' => 'kg',
            'notes' => 'Curry preparation kitchen usage',
        ];

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        $onion->refresh();
        $this->assertEquals(20.0, (float) $onion->current_stock);
    }

    public function test_consumption_blocks_negative_stock(): void
    {
        $ginger = $this->createProduct('Ginger (আদা)', 120.00, 2.00);

        $payload = [
            'transaction_type' => 'consumption',
            'transaction_date' => now()->toDateString(),
            'product_id' => $ginger->id,
            'quantity' => 10, // Trying to consume 10kg when only 2kg exists
            'unit' => 'kg',
            'notes' => 'Over consumption attempt',
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertSessionHasErrors(['items']);

        $ginger->refresh();
        $this->assertEquals(2.0, (float) $ginger->current_stock);
    }

    public function test_operating_expense_cost_entry_records_expense_and_withdrawal_without_duplicate(): void
    {
        $payload = [
            'transaction_type' => 'expense',
            'transaction_date' => now()->toDateString(),
            'expense_category' => 'electricity',
            'name' => 'Electricity Pre-paid Meter Recharge',
            'amount' => 1500.00,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'Token recharged via cash',
        ];

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        // Operating expense record created
        $this->assertDatabaseHas('operating_expenses', [
            'category' => 'electricity',
            'amount' => 1500.00,
        ]);

        // Cash account reduced exactly once (10000 - 1500 = 8500)
        $this->cashAccount->refresh();
        $this->assertEquals(8500.00, (float) $this->cashAccount->current_balance);
    }

    public function test_staff_salary_cost_entry_reduces_payable_and_withdraws_from_account_without_duplicate_expense(): void
    {
        $payload = [
            'transaction_type' => 'salary_payment',
            'transaction_date' => now()->toDateString(),
            'employee_id' => $this->employee->id,
            'amount' => 5000.00,
            'payment_account_id' => $this->cashAccount->id,
            'salary_month' => '2026-11',
            'notes' => 'Partial November salary payout in cash',
        ];

        $initialExpenseCount = OperatingExpense::count();

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        // Cash account reduced by 5000
        $this->cashAccount->refresh();
        $this->assertEquals(5000.00, (float) $this->cashAccount->current_balance);

        // Crucial: No extra operating expense record created
        $this->assertEquals($initialExpenseCount, OperatingExpense::count());

        // Verify salary payment record exists
        $this->assertDatabaseHas('salary_payments', [
            'employee_id' => $this->employee->id,
            'payment_type' => 'salary_payment',
            'amount' => 5000.00,
        ]);
    }

    public function test_staff_advance_cost_entry_records_advance_without_impacting_operating_expenses(): void
    {
        $payload = [
            'transaction_type' => 'salary_advance',
            'transaction_date' => now()->toDateString(),
            'employee_id' => $this->employee->id,
            'amount' => 2000.00,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'Medical advance request',
        ];

        $initialExpenseCount = OperatingExpense::count();

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        // Employee advance record created
        $this->assertDatabaseHas('employee_advances', [
            'employee_id' => $this->employee->id,
            'amount' => 2000.00,
        ]);

        // Cash account reduced
        $this->cashAccount->refresh();
        $this->assertEquals(8000.00, (float) $this->cashAccount->current_balance);

        // No operating expense created
        $this->assertEquals($initialExpenseCount, OperatingExpense::count());
    }

    public function test_supplier_payment_cost_entry_allocates_against_bills_and_reduces_payable(): void
    {
        // First simulate an existing supplier due
        $this->supplier->update([
            'current_balance' => 3000.00,
        ]);

        $payload = [
            'transaction_type' => 'supplier_payment',
            'transaction_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'amount' => 2000.00,
            'payment_account_id' => $this->cashAccount->id,
            'notes' => 'Clearing vegetable supplier due',
        ];

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        $this->supplier->refresh();
        $this->assertEquals(1000.00, (float) $this->supplier->current_balance);

        $this->cashAccount->refresh();
        $this->assertEquals(8000.00, (float) $this->cashAccount->current_balance);
    }

    public function test_saved_cost_template_lifecycle_create_toggle_favorite_and_delete(): void
    {
        $template = SavedCostTemplate::create([
            'name' => 'Green Chili (কাঁচা মরিচ)',
            'transaction_type' => 'purchase',
            'category_name' => 'Vegetables',
            'default_unit' => 'kg',
            'last_unit_price' => 120.00,
            'preferred_unit_price' => 120.00,
            'is_favorite' => false,
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse($template->is_favorite);

        // Toggle favorite
        $response = $this->actingAs($this->user)->post("/daily-costs/templates/{$template->id}/favorite");
        $response->assertRedirect();
        $template->refresh();
        $this->assertTrue($template->is_favorite);

        // Delete (deactivate) template
        $delResponse = $this->actingAs($this->user)->delete("/daily-costs/templates/{$template->id}");
        $delResponse->assertRedirect();
        $template->refresh();
        $this->assertFalse($template->is_active);
    }

    public function test_cost_entry_auto_saves_shortcut_template_when_flagged(): void
    {
        $lemon = $this->createProduct('Lemon (লেবু)', 5.00, 0.00);

        $payload = [
            'transaction_type' => 'purchase',
            'transaction_date' => now()->toDateString(),
            'name' => 'Lemon (লেবু)',
            'product_id' => $lemon->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 10,
            'unit' => 'kg',
            'unit_price' => 60.00,
            'amount' => 600.00,
            'payment_account_id' => $this->cashAccount->id,
            'paid_amount' => 600.00,
            'save_as_template' => true,
        ];

        $response = $this->actingAs($this->user)->post('/daily-costs', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('saved_cost_templates', [
            'name' => 'Lemon (লেবু)',
            'transaction_type' => 'purchase',
            'last_unit_price' => 60.00,
        ]);
    }

    public function test_reusing_saved_template_with_new_price_and_quantity_does_not_mutate_template_defaults_prematurely(): void
    {
        $sugar = $this->createProduct('Sugar (চিনি)', 135.00, 0.00);

        $template = SavedCostTemplate::create([
            'name' => 'Sugar (চিনি)',
            'transaction_type' => 'purchase',
            'product_id' => $sugar->id,
            'category_name' => 'Groceries',
            'default_unit' => 'kg',
            'last_unit_price' => 135.00,
            'is_favorite' => true,
            'use_count' => 5,
            'created_by' => $this->user->id,
        ]);

        // Reusing template with 140.00 price and updating template
        $payload = [
            'transaction_type' => 'purchase',
            'transaction_date' => now()->toDateString(),
            'product_id' => $sugar->id,
            'quantity' => 50,
            'unit' => 'kg',
            'unit_price' => 140.00,
            'amount' => 7000.00,
            'payment_account_id' => $this->cashAccount->id,
            'paid_amount' => 7000.00,
            'template_id' => $template->id,
        ];

        $this->actingAs($this->user)->post('/daily-costs', $payload);

        $template->refresh();
        $this->assertEquals(140.00, (float) $template->last_unit_price);
        $this->assertEquals(6, $template->use_count);
    }

    public function test_cash_reconciliation_core_formula_calculation(): void
    {
        // Formula:
        // Estimated Cash Sales = Closing Cash - Opening Cash - Other Cash Receipts + Cash Payments
        // Example:
        // Opening = 5,000
        // Cash Payments = 2,000 (Expense 2,000 paid from cash account)
        // Other Receipts = 0
        // Closing Cash counted = 8,000
        // Expected Estimated Cash Sales = 8,000 - 5,000 - 0 + 2,000 = 5,000.

        $today = now()->toDateString();

        // 1. Record yesterday's reconciliation with closing cash 5000
        $yesterday = now()->subDay()->toDateString();
        DailyCashReconciliation::create([
            'reconciliation_date' => $yesterday,
            'opening_cash' => 4000.00,
            'counted_closing_cash' => 5000.00,
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        // 2. Record a cash withdrawal of 2000 today (operating expense)
        $this->smartCostService->recordCost([
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'expense_category' => 'repairs',
            'name' => 'Plumbing fix',
            'amount' => 2000.00,
            'payment_account_id' => $this->cashAccount->id,
        ], $this->user->id);

        // 3. Count closing cash as 8000
        DailyCashReconciliation::create([
            'reconciliation_date' => $today,
            'opening_cash' => 5000.00,
            'counted_closing_cash' => 8000.00,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // 4. Retrieve computed reconciliation data
        $reconData = $this->cashReconciliationService->getReconciliationData($today);

        $this->assertEquals(5000.00, $reconData['opening_cash']);
        $this->assertEquals(2000.00, $reconData['today_cash_payments']);
        $this->assertEquals(0.00, $reconData['other_cash_receipts']);
        $this->assertEquals(8000.00, $reconData['counted_closing_cash']);
        // 8000 - 5000 - 0 + 2000 = 5000
        $this->assertEquals(5000.00, $reconData['estimated_cash_sales']);
    }

    public function test_non_cash_payments_like_bkash_do_not_affect_cash_drawer_reconciliation(): void
    {
        $today = now()->toDateString();

        // Paid via bKash (Not Cash Drawer)
        $this->smartCostService->recordCost([
            'transaction_type' => 'expense',
            'transaction_date' => $today,
            'expense_category' => 'cleaning',
            'name' => 'Cleaning supplies via bKash',
            'amount' => 1200.00,
            'payment_account_id' => $this->bkashAccount->id,
        ], $this->user->id);

        $reconData = $this->cashReconciliationService->getReconciliationData($today);

        // Today's cash drawer payments must still be 0.00!
        $this->assertEquals(0.00, $reconData['today_cash_payments']);
    }

    public function test_reconciliation_detects_other_cash_receipts_such_as_capital_contributions(): void
    {
        $today = now()->toDateString();

        // Deposit directly to cash drawer (e.g. Owner capital injection)
        $accountService = app(FinancialAccountService::class);
        $accountService->deposit(
            accountId: $this->cashAccount->id,
            amount: 3000.00,
            date: $today,
            referenceType: null,
            referenceId: null,
            userId: $this->user->id,
            notes: 'Owner personal cash injection'
        );

        $reconData = $this->cashReconciliationService->getReconciliationData($today);

        $this->assertEquals(3000.00, $reconData['other_cash_receipts']);
    }

    public function test_reconciliation_generates_warnings_for_negative_sales_and_missing_counts(): void
    {
        $today = now()->toDateString();

        // 1. Missing closing cash count
        $dataMissingClosing = $this->cashReconciliationService->getReconciliationData($today);
        $this->assertContains(
            'Closing cash has not been physically counted yet. Please count the drawer cash to compute estimated sales.',
            $dataMissingClosing['warnings']
        );

        // 2. Negative estimated sales scenario:
        // Opening = 5000, Closing counted = 1000, Payments = 500 -> 1000 - 5000 + 500 = -3500!
        DailyCashReconciliation::create([
            'reconciliation_date' => $today,
            'opening_cash' => 5000.00,
            'counted_closing_cash' => 1000.00,
            'today_cash_payments' => 500.00,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $dataNegative = $this->cashReconciliationService->getReconciliationData($today);
        $this->assertLessThan(0, $dataNegative['estimated_cash_sales']);
        $hasNegativeWarning = false;
        foreach ($dataNegative['warnings'] as $warning) {
            if (str_contains($warning, 'Estimated cash sales is negative')) {
                $hasNegativeWarning = true;
                break;
            }
        }
        $this->assertTrue($hasNegativeWarning);
    }

    public function test_sync_estimated_cash_sales_to_daily_sales_updates_pos_revenue_accurately(): void
    {
        $today = now()->toDateString();

        // Create initial daily sales with bKash = 1000, cash = 0
        DailySale::create([
            'sales_date' => $today,
            'cash_sales' => 0.00,
            'bkash_sales' => 1000.00,
            'nagad_sales' => 500.00,
            'card_bank_sales' => 0.00,
            'other_sales' => 0.00,
            'total_sales' => 1500.00,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'date' => $today,
            'estimated_cash_sales' => 4500.00,
        ];

        $response = $this->actingAs($this->user)->post('/cash-reconciliation/sync-sales', $payload);
        $response->assertRedirect();

        $sale = DailySale::whereDate('sales_date', $today)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(4500.00, (float) $sale->cash_sales);
        // Total = 4500 (cash) + 1000 (bKash) + 500 (Nagad) = 6000.00
        $this->assertEquals(6000.00, (float) $sale->total_sales);
    }

    public function test_saving_daily_cash_reconciliation_draft_and_confirmed_status(): void
    {
        $today = now()->toDateString();

        $payload = [
            'reconciliation_date' => $today,
            'opening_cash' => 5000.00,
            'counted_closing_cash' => 9500.00,
            'yesterday_closing_cash' => 5000.00,
            'other_cash_receipts' => 0.00,
            'today_cash_payments' => 1500.00,
            'status' => 'confirmed',
            'notes' => 'Everything balanced accurately with evening cash count.',
        ];

        $response = $this->actingAs($this->user)->post('/cash-reconciliation', $payload);
        $response->assertRedirect();

        $recon = DailyCashReconciliation::whereDate('reconciliation_date', $today)->first();
        $this->assertNotNull($recon);
        $this->assertEquals('confirmed', $recon->status);
        $this->assertEquals(9500.00, (float) $recon->counted_closing_cash);
        $this->assertEquals(6000.00, (float) $recon->estimated_cash_sales); // 9500 - 5000 + 1500
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get('/daily-costs')->assertRedirect('/login');
        $this->get('/cash-reconciliation')->assertRedirect('/login');
    }
}
