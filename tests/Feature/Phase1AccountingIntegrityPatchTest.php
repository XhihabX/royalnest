<?php

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Consumption;
use App\Models\DailyCashReconciliation;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1AccountingIntegrityPatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $cashier;

    protected FinancialAccount $cashAccount;

    protected FinancialAccount $bkashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'primary_role' => 'manager',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'primary_role' => 'cashier',
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
    }

    /**
     * 1. Missing or inactive required financial account causes complete validation error and rollback.
     */
    public function test_missing_or_inactive_financial_account_rolls_back_entire_sales_operation(): void
    {
        $this->actingAs($this->manager);

        // Attempt to record Nagad sales when no active Nagad account exists in financial_accounts
        $date = '2026-11-26';
        $response = $this->post(route('sales.store'), [
            'sales_date' => $date,
            'total_sales' => 5000.00,
            'cash_sales' => 2000.00,
            'nagad_sales' => 3000.00, // No Nagad account created!
        ]);

        $response->assertSessionHasErrors(['nagad_sales']);

        // Assert zero records in daily_sales and zero transactions
        $this->assertDatabaseMissing('daily_sales', ['sales_date' => $date]);
        $this->assertEquals(5000.00, (float) $this->cashAccount->fresh()->current_balance);
        $this->assertEquals(0, AccountTransaction::where('transaction_date', $date)->count());
    }

    /**
     * 2. Inactive account causes validation error and complete rollback.
     */
    public function test_inactive_financial_account_blocks_sale_and_rolls_back(): void
    {
        $this->actingAs($this->manager);

        $this->bkashAccount->update(['is_active' => false]);

        $date = '2026-11-27';
        $response = $this->post(route('sales.store'), [
            'sales_date' => $date,
            'total_sales' => 4000.00,
            'cash_sales' => 2000.00,
            'bkash_sales' => 2000.00,
        ]);

        $response->assertSessionHasErrors(['bkash_sales']);
        $this->assertDatabaseMissing('daily_sales', ['sales_date' => $date]);
        $this->assertEquals(5000.00, (float) $this->cashAccount->fresh()->current_balance);
    }

    /**
     * 3. Calculator identical submission does not duplicate FIFO consumption, even with changed notes.
     */
    public function test_calculator_changed_notes_does_not_duplicate_fifo_consumption(): void
    {
        $this->actingAs($this->manager);

        $category = Category::create(['name' => 'Poultry', 'slug' => 'poultry']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chicken Meat',
            'code' => 'CHK-99',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 200.00,
            'current_stock' => 50.00,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'batch_no' => 'BATCH-TEST-CHK',
            'initial_quantity' => 50.00,
            'remaining_quantity' => 50.00,
            'unit_cost' => 200.00,
            'received_date' => '2026-11-20',
        ]);

        $date = '2026-11-28';
        $payload1 = [
            'date' => $date,
            'items' => [
                ['product_id' => $product->id, 'quantity_used' => 10.00],
            ],
            'notes' => 'First submission note',
        ];

        $this->post(route('calculator.store'), $payload1)->assertRedirect();
        $this->assertEquals(40.00, (float) $product->fresh()->current_stock);
        $this->assertEquals(1, Consumption::whereDate('consumption_date', $date)->count());

        // Repeated submission with DIFFERENT note but same logical items/quantities
        $payload2 = [
            'date' => $date,
            'items' => [
                ['product_id' => $product->id, 'quantity_used' => 10.00],
            ],
            'notes' => 'Second submission with completely different note string',
        ];

        $this->post(route('calculator.store'), $payload2)->assertRedirect();
        // Should NOT deduct stock again!
        $this->assertEquals(40.00, (float) $product->fresh()->current_stock);
        $this->assertEquals(1, Consumption::whereDate('consumption_date', $date)->count());
    }

    /**
     * 4. Calculator allows additive new consumption when quantities or items differ.
     */
    public function test_calculator_additive_consumption_when_items_differ(): void
    {
        $this->actingAs($this->manager);

        $category = Category::create(['name' => 'Spices', 'slug' => 'spices']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Garlic Paste',
            'code' => 'GAR-01',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 150.00,
            'current_stock' => 30.00,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'batch_no' => 'BATCH-GAR-01',
            'initial_quantity' => 30.00,
            'remaining_quantity' => 30.00,
            'unit_cost' => 150.00,
            'received_date' => '2026-11-20',
        ]);

        $date = '2026-11-29';
        // Morning usage: 5 kg
        $this->post(route('calculator.store'), [
            'date' => $date,
            'items' => [['product_id' => $product->id, 'quantity_used' => 5.00]],
            'notes' => 'Morning prep usage',
        ])->assertRedirect();

        $this->assertEquals(25.00, (float) $product->fresh()->current_stock);

        // Evening second shift usage: additional 4 kg
        $this->post(route('calculator.store'), [
            'date' => $date,
            'items' => [['product_id' => $product->id, 'quantity_used' => 4.00]],
            'notes' => 'Evening shift usage',
        ])->assertRedirect();

        $this->assertEquals(21.00, (float) $product->fresh()->current_stock);
        $this->assertEquals(2, Consumption::whereDate('consumption_date', $date)->count());
    }

    /**
     * 5. Manual cash sales are not silently overwritten by reconciliation without explicit confirmation.
     */
    public function test_manual_cash_sales_protected_from_unconfirmed_reconciliation_overwrite(): void
    {
        $this->actingAs($this->manager);

        $date = '2026-11-30';
        $this->post(route('sales.store'), [
            'sales_date' => $date,
            'total_sales' => 10000.00,
            'cash_sales' => 8000.00,
            'bkash_sales' => 2000.00,
        ])->assertRedirect();

        // Attempting to sync estimated cash sales of 7500 without confirm_overwrite flag must be rejected
        $response = $this->post(route('cash-reconciliation.sync-sales'), [
            'date' => $date,
            'estimated_cash_sales' => 7500.00,
            'confirm_overwrite' => false,
        ]);

        $response->assertSessionHasErrors(['estimated_cash_sales']);

        // Cash sales must remain unchanged at 8000
        $sale = DailySale::whereDate('sales_date', $date)->first();
        $this->assertEquals(8000.00, (float) $sale->cash_sales);
        $this->assertEquals(13000.00, (float) $this->cashAccount->fresh()->current_balance); // 5000 + 8000
    }

    /**
     * 6. Closed / Confirmed reconciliation cannot be synced by unauthorized staff.
     */
    public function test_confirmed_reconciliation_cannot_be_synced_by_non_manager(): void
    {
        $this->actingAs($this->cashier);

        $date = '2026-12-01';
        DailyCashReconciliation::create([
            'reconciliation_date' => $date,
            'opening_cash' => 5000.00,
            'counted_closing_cash' => 12000.00,
            'estimated_cash_sales' => 7000.00,
            'status' => 'confirmed',
            'created_by' => $this->manager->id,
            'confirmed_by' => $this->manager->id,
        ]);

        $response = $this->post(route('cash-reconciliation.sync-sales'), [
            'date' => $date,
            'estimated_cash_sales' => 7000.00,
        ]);

        $response->assertSessionHasErrors(['date']);
    }
}
