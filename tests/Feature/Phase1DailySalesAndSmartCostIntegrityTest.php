<?php

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Consumption;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1DailySalesAndSmartCostIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected FinancialAccount $cashAccount;
    protected FinancialAccount $bkashAccount;
    protected FinancialAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'primary_role' => 'manager',
            'is_active' => true,
        ]);

        $this->cashAccount = FinancialAccount::create([
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'opening_balance' => 5000.00,
            'current_balance' => 5000.00,
            'is_active' => true,
        ]);

        $this->bkashAccount = FinancialAccount::create([
            'name' => 'bKash Merchant',
            'type' => 'bkash',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        $this->bankAccount = FinancialAccount::create([
            'name' => 'BRAC Bank Current',
            'type' => 'bank',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'is_active' => true,
        ]);
    }

    public function test_manual_sales_creation_posts_exact_channel_deposits(): void
    {
        $this->actingAs($this->manager);

        $date = '2026-11-20';
        $payload = [
            'sales_date' => $date,
            'total_sales' => 12500.00,
            'cash_sales' => 7500.00,
            'bkash_sales' => 3000.00,
            'nagad_sales' => 0.00,
            'card_bank_sales' => 2000.00,
            'other_sales' => 0.00,
            'notes' => 'Evening peak dinner sales',
        ];

        $response = $this->post(route('sales.store'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('daily_sales', [
            'sales_date' => $date,
            'total_sales' => 12500.00,
            'cash_sales' => 7500.00,
            'bkash_sales' => 3000.00,
            'card_bank_sales' => 2000.00,
            'discrepancy_amount' => 0.00,
        ]);

        $this->assertEquals(12500.00, (float) $this->cashAccount->fresh()->current_balance); // 5000 + 7500
        $this->assertEquals(3000.00, (float) $this->bkashAccount->fresh()->current_balance); // 0 + 3000
        $this->assertEquals(12000.00, (float) $this->bankAccount->fresh()->current_balance); // 10000 + 2000

        // Zero-value channels must not create unnecessary transactions
        $this->assertEquals(3, AccountTransaction::where('reference_type', DailySale::class)->count());
    }

    public function test_manual_sales_update_reverses_previous_deposits_exactly_once(): void
    {
        $this->actingAs($this->manager);

        $date = '2026-11-21';
        $initialPayload = [
            'sales_date' => $date,
            'total_sales' => 10000.00,
            'cash_sales' => 6000.00,
            'bkash_sales' => 4000.00,
            'card_bank_sales' => 0.00,
        ];
        $this->post(route('sales.store'), $initialPayload)->assertRedirect();

        $this->assertEquals(11000.00, (float) $this->cashAccount->fresh()->current_balance); // 5000 + 6000
        $this->assertEquals(4000.00, (float) $this->bkashAccount->fresh()->current_balance); // 0 + 4000

        // Now cashier discovers a correction: cash was 5000, bkash 5000
        $updatedPayload = [
            'sales_date' => $date,
            'total_sales' => 10000.00,
            'cash_sales' => 5000.00,
            'bkash_sales' => 5000.00,
            'card_bank_sales' => 0.00,
        ];
        $this->post(route('sales.store'), $updatedPayload)->assertRedirect();

        // Exact balance should reflect reversed 6000 and added 5000 = net -1000 from 11000 -> 10000
        $this->assertEquals(10000.00, (float) $this->cashAccount->fresh()->current_balance);
        $this->assertEquals(5000.00, (float) $this->bkashAccount->fresh()->current_balance);

        // Immutable Ledger: Active transaction records must equal current channels (2),
        // while total records must preserve complete audit history (6: 2 original + 2 compensating + 2 new)
        $this->assertEquals(2, AccountTransaction::where('reference_type', DailySale::class)->where('reversal_status', 'active')->count());
        $this->assertEquals(6, AccountTransaction::where('reference_type', DailySale::class)->count());
    }

    public function test_calculator_sales_creation_synchronizes_financial_accounts(): void
    {
        $this->actingAs($this->manager);

        $category = Category::create(['name' => 'Dairy', 'slug' => 'dairy']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Cow Milk',
            'code' => 'MILK-01',
            'base_unit' => 'liter',
            'purchase_unit' => 'liter',
            'cost_price' => 80.00,
            'current_stock' => 50.00,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'batch_no' => 'BATCH-MILK-1',
            'initial_quantity' => 50.00,
            'remaining_quantity' => 50.00,
            'unit_cost' => 80.00,
            'received_date' => '2026-11-20',
        ]);

        $date = '2026-11-22';
        $payload = [
            'date' => $date,
            'sales' => [
                'total_sales' => 8000.00,
                'cash_sales' => 5000.00,
                'bkash_sales' => 3000.00,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_used' => 10.00,
                ],
            ],
            'notes' => 'P&L test session',
        ];

        $response = $this->post(route('calculator.store'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify DailySale is created
        $this->assertDatabaseHas('daily_sales', [
            'sales_date' => $date,
            'total_sales' => 8000.00,
            'cash_sales' => 5000.00,
            'bkash_sales' => 3000.00,
        ]);

        // CRITICAL: Financial accounts must be synchronized!
        $this->assertEquals(10000.00, (float) $this->cashAccount->fresh()->current_balance); // 5000 + 5000
        $this->assertEquals(3000.00, (float) $this->bkashAccount->fresh()->current_balance); // 0 + 3000

        // FIFO consumption must be deducted
        $this->assertEquals(40.00, (float) $product->fresh()->current_stock);
    }

    public function test_repeated_calculator_submission_does_not_duplicate_fifo_consumption(): void
    {
        $this->actingAs($this->manager);

        $category = Category::create(['name' => 'Poultry', 'slug' => 'poultry']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chicken Breast',
            'code' => 'CHK-01',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 250.00,
            'current_stock' => 20.00,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'batch_no' => 'BATCH-CHK-1',
            'initial_quantity' => 20.00,
            'remaining_quantity' => 20.00,
            'unit_cost' => 250.00,
            'received_date' => '2026-11-20',
        ]);

        $date = '2026-11-23';
        $payload = [
            'date' => $date,
            'sales' => [
                'total_sales' => 5000.00,
                'cash_sales' => 5000.00,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_used' => 5.00,
                ],
            ],
            'notes' => 'Duplicate submission check note',
        ];

        // First submission
        $this->post(route('calculator.store'), $payload)->assertRedirect();
        $this->assertEquals(15.00, (float) $product->fresh()->current_stock);
        $this->assertEquals(1, Consumption::whereDate('consumption_date', $date)->count());

        // Repeated submission with same notes/items/date
        $this->post(route('calculator.store'), $payload)->assertRedirect();
        // Should NOT re-consume
        $this->assertEquals(15.00, (float) $product->fresh()->current_stock);
        $this->assertEquals(1, Consumption::whereDate('consumption_date', $date)->count());
    }

    public function test_cash_reconciliation_sync_synchronizes_without_duplicating_non_cash_sales(): void
    {
        $this->actingAs($this->manager);

        $date = '2026-11-24';

        // Pre-existing manual sales with bKash
        $this->post(route('sales.store'), [
            'sales_date' => $date,
            'total_sales' => 7000.00,
            'cash_sales' => 4000.00,
            'bkash_sales' => 3000.00,
        ])->assertRedirect();

        $this->assertEquals(9000.00, (float) $this->cashAccount->fresh()->current_balance); // 5000 + 4000
        $this->assertEquals(3000.00, (float) $this->bkashAccount->fresh()->current_balance);

        // Evening cash drawer reconciliation derives estimated cash sales = 4500 (with explicit confirmation to overwrite variance)
        $this->post(route('cash-reconciliation.sync-sales'), [
            'date' => $date,
            'estimated_cash_sales' => 4500.00,
            'confirm_overwrite' => true,
        ])->assertRedirect();

        $sale = DailySale::whereDate('sales_date', $date)->first();
        $this->assertEquals(4500.00, (float) $sale->cash_sales);
        $this->assertEquals(3000.00, (float) $sale->bkash_sales);
        $this->assertEquals(7500.00, (float) $sale->total_sales);

        // Cash account must update to 5000 + 4500 = 9500
        $this->assertEquals(9500.00, (float) $this->cashAccount->fresh()->current_balance);
        // Non-cash account must remain exactly 3000, not doubled
        $this->assertEquals(3000.00, (float) $this->bkashAccount->fresh()->current_balance);
    }

    public function test_smart_cost_purchase_subtotal_accuracy(): void
    {
        $this->actingAs($this->manager);

        $category = Category::create(['name' => 'Vegetables', 'slug' => 'veg']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Onion Local',
            'code' => 'ONION-01',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 110.00,
            'product_type' => 'raw_material',
            'current_stock' => 100.00,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Vegetable Wholesaler',
            'phone' => '01700998877',
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        $date = '2026-11-25';
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-TEST-99',
            'purchase_date' => $date,
            'total_amount' => 5500.00,
            'paid_amount' => 5500.00,
            'status' => 'confirmed',
            'created_by' => $this->manager->id,
        ]);

        // PurchaseItem schema has: purchase_quantity, unit_price, subtotal
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'purchase_quantity' => 50.00,
            'purchase_unit' => 'kg',
            'unit_price' => 110.00,
            'subtotal' => 5500.00,
            'base_quantity' => 50.00,
            'base_unit_cost' => 110.00,
        ]);

        $response = $this->get(route('daily-costs.index', ['date' => $date]));
        $response->assertOk();

        // Check dailySummary prop passed to Inertia view
        $summary = $response->viewData('page')['props']['dailySummary'];
        $this->assertEquals(5500.00, (float) $summary['raw_material_purchases']);
    }
}
