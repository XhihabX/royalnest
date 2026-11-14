<?php

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Consumption;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\DailySalesService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase1ImmutableLedgerAndConsumptionRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected FinancialAccount $cashAccount;
    protected FinancialAccount $bkashAccount;
    protected FinancialAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Branch::firstOrCreate(['id' => 1], [
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['primary_role' => 'owner', 'is_active' => true, 'branch_id' => 1]);

        $this->cashAccount = FinancialAccount::create([
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'is_active' => true,
        ]);

        $this->bkashAccount = FinancialAccount::create([
            'name' => 'bKash Merchant',
            'type' => 'bkash',
            'opening_balance' => 5000.00,
            'current_balance' => 5000.00,
            'is_active' => true,
        ]);

        $this->bankAccount = FinancialAccount::create([
            'name' => 'City Bank Account',
            'type' => 'bank',
            'opening_balance' => 20000.00,
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);
    }

    public function test_sales_update_preserves_original_transaction_rows(): void
    {
        $dailySalesService = app(DailySalesService::class);
        $date = '2026-11-20';

        // 1. Initial sale creation: Cash ৳3,000, bKash ৳1,000
        $result1 = $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 4000.00,
            'cash_sales' => 3000.00,
            'bkash_sales' => 1000.00,
        ], $this->user->id);

        $initialTxns = AccountTransaction::where('reference_type', DailySale::class)
            ->where('reference_id', $result1['sale']->id)
            ->get();

        $this->assertCount(2, $initialTxns);
        $this->assertTrue($initialTxns->every(fn ($t) => $t->reversal_status === 'active'));

        $origCashTxnId = $initialTxns->firstWhere('financial_account_id', $this->cashAccount->id)->id;

        // 2. Update sale: Cash ৳5,000, bKash ৳2,000
        $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 7000.00,
            'cash_sales' => 5000.00,
            'bkash_sales' => 2000.00,
        ], $this->user->id);

        // Verify the original cash transaction was NEVER deleted
        $originalTxn = AccountTransaction::find($origCashTxnId);
        $this->assertNotNull($originalTxn, 'Original transaction row must not be deleted from account_transactions table');
        $this->assertEquals('reversed', $originalTxn->reversal_status);
        $this->assertNotNull($originalTxn->reversed_at);
        $this->assertEquals($this->user->id, $originalTxn->reversed_by);
        $this->assertNotNull($originalTxn->compensating_transaction_id);

        // Total transactions for this DailySale: 2 original (reversed) + 2 compensating + 2 new active = 6
        $allTxns = AccountTransaction::where('reference_type', DailySale::class)
            ->where('reference_id', $result1['sale']->id)
            ->get();
        $this->assertCount(6, $allTxns);
    }

    public function test_compensating_reversal_references_original_via_reversal_of_id(): void
    {
        $dailySalesService = app(DailySalesService::class);
        $date = '2026-11-21';

        $result = $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 2500.00,
            'cash_sales' => 2500.00,
        ], $this->user->id);

        $originalDeposit = AccountTransaction::where('reference_type', DailySale::class)
            ->where('reference_id', $result['sale']->id)
            ->first();

        // Update sales to ৳4,000
        $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 4000.00,
            'cash_sales' => 4000.00,
        ], $this->user->id);

        $compensatingTxn = AccountTransaction::where('reversal_of_id', $originalDeposit->id)->first();
        $this->assertNotNull($compensatingTxn);
        $this->assertEquals('withdrawal', $compensatingTxn->type);
        $this->assertEquals(2500.00, (float) $compensatingTxn->amount);
        $this->assertEquals('compensating', $compensatingTxn->reversal_status);
        $this->assertEquals($originalDeposit->id, $compensatingTxn->reversal_of_id);

        $originalDeposit->refresh();
        $this->assertEquals($compensatingTxn->id, $originalDeposit->compensating_transaction_id);
    }

    public function test_repeated_sales_correction_creates_audit_chain_without_balance_drift(): void
    {
        $dailySalesService = app(DailySalesService::class);
        $date = '2026-11-22';
        $initialBalance = (float) $this->cashAccount->current_balance;

        // Edit 1: ৳1,000
        $result = $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 1000.00,
            'cash_sales' => 1000.00,
        ], $this->user->id);

        $this->cashAccount->refresh();
        $this->assertEquals($initialBalance + 1000.00, (float) $this->cashAccount->current_balance);

        // Edit 2: ৳1,500
        $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 1500.00,
            'cash_sales' => 1500.00,
        ], $this->user->id);

        $this->cashAccount->refresh();
        $this->assertEquals($initialBalance + 1500.00, (float) $this->cashAccount->current_balance);

        // Edit 3: ৳1,200
        $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 1200.00,
            'cash_sales' => 1200.00,
        ], $this->user->id);

        $this->cashAccount->refresh();
        $this->assertEquals($initialBalance + 1200.00, (float) $this->cashAccount->current_balance);

        // Verify active transactions sum exactly equals ৳1,200
        $activeSum = AccountTransaction::where('reference_type', DailySale::class)
            ->where('reference_id', $result['sale']->id)
            ->where('reversal_status', 'active')
            ->sum('amount');
        $this->assertEquals(1200.00, (float) $activeSum);
    }

    public function test_calculator_quantity_revision_adjusts_stock_correctly(): void
    {
        $category = Category::create(['name' => 'Meat & Poultry', 'slug' => 'meat-poultry', 'category_type' => 'raw_material']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Fresh Beef',
            'code' => 'BEEF-01',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 700.00,
            'current_stock' => 50.0000,
            'is_active' => true,
        ]);

        $stockLedgerService = app(StockLedgerService::class);
        $stockLedgerService->createBatch($product, 50.0000, 700.00, '2026-11-20', null, 'BATCH-BEEF-01');

        $this->actingAs($this->user);

        // 1. Initial Calculator submit: 10 kg Beef used
        $response1 = $this->post(route('calculator.store'), [
            'date' => '2026-11-20',
            'items' => [
                ['product_id' => $product->id, 'quantity_used' => 10.0],
            ],
            'sales' => [
                'cash_sales' => 5000.00,
            ],
        ]);
        $response1->assertRedirect();

        $product->refresh();
        $this->assertEquals(40.0000, (float) $product->current_stock);

        $consumption = Consumption::whereDate('consumption_date', '2026-11-20')->first();
        $this->assertNotNull($consumption);
        $this->assertEquals(7000.00, (float) $consumption->total_cost);

        // 2. Revision: Chef corrects to 12 kg Beef used (passes consumption_id)
        $response2 = $this->post(route('calculator.store'), [
            'date' => '2026-11-20',
            'consumption_id' => $consumption->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_used' => 12.0],
            ],
            'sales' => [
                'cash_sales' => 5000.00,
            ],
        ]);
        $response2->assertRedirect();

        $product->refresh();
        // 50kg starting - 12kg corrected = 38kg remaining (NOT 50 - 10 - 12 = 28kg)
        $this->assertEquals(38.0000, (float) $product->current_stock);

        $consumption->refresh();
        $this->assertEquals('revised', $consumption->status);
        $this->assertEquals(8400.00, (float) $consumption->total_cost); // 12 * 700
    }

    public function test_calculator_quantity_reduction_restores_fifo_batches(): void
    {
        $category = Category::create(['name' => 'Dairy', 'slug' => 'dairy', 'category_type' => 'raw_material']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Liquid Milk',
            'code' => 'MILK-01',
            'base_unit' => 'L',
            'purchase_unit' => 'L',
            'cost_price' => 90.00,
            'current_stock' => 20.0000,
            'is_active' => true,
        ]);

        $stockLedgerService = app(StockLedgerService::class);
        $batch = $stockLedgerService->createBatch($product, 20.0000, 90.00, '2026-11-20', null, 'BATCH-MILK-01');

        $this->actingAs($this->user);

        // Consume 10 L
        $this->post(route('calculator.store'), [
            'date' => '2026-11-20',
            'items' => [
                ['product_id' => $product->id, 'quantity_used' => 10.0],
            ],
        ]);

        $batch->refresh();
        $this->assertEquals(10.0000, (float) $batch->remaining_quantity);

        $consumption = Consumption::whereDate('consumption_date', '2026-11-20')->first();

        // Revise DOWN to 6 L (reduction of 4 L)
        $this->post(route('calculator.store'), [
            'date' => '2026-11-20',
            'consumption_id' => $consumption->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_used' => 6.0],
            ],
        ]);

        $batch->refresh();
        // Batch remaining quantity should be restored to 20 - 6 = 14 L
        $this->assertEquals(14.0000, (float) $batch->remaining_quantity);

        $product->refresh();
        $this->assertEquals(14.0000, (float) $product->current_stock);
    }

    public function test_missing_bkash_account_blocks_calculator_and_manual_sales(): void
    {
        // Deactivate bKash account
        $this->bkashAccount->update(['is_active' => false]);

        $dailySalesService = app(DailySalesService::class);

        $this->expectException(ValidationException::class);
        $dailySalesService->recordDailySale([
            'sales_date' => '2026-11-23',
            'total_sales' => 1000.00,
            'cash_sales' => 500.00,
            'bkash_sales' => 500.00,
        ], $this->user->id);
    }

    public function test_bkash_does_not_fall_back_to_cash_in_any_entry_point(): void
    {
        $this->bkashAccount->delete(); // completely remove bkash account
        $this->actingAs($this->user);

        // 1. Manual sales attempt
        $res1 = $this->post('/sales', [
            'sales_date' => '2026-11-24',
            'total_sales' => 1500.00,
            'cash_sales' => 1000.00,
            'bkash_sales' => 500.00,
        ]);
        $res1->assertSessionHasErrors('bkash_sales');

        // 2. Calculator sales attempt
        $res2 = $this->post(route('calculator.store'), [
            'date' => '2026-11-24',
            'sales' => [
                'total_sales' => 1500.00,
                'cash_sales' => 1000.00,
                'bkash_sales' => 500.00,
            ],
        ]);
        $res2->assertSessionHasErrors('bkash_sales');
    }

    public function test_zero_value_channels_create_no_ledger_entries(): void
    {
        $dailySalesService = app(DailySalesService::class);
        $date = '2026-11-25';

        $result = $dailySalesService->recordDailySale([
            'sales_date' => $date,
            'total_sales' => 2000.00,
            'cash_sales' => 2000.00,
            'bkash_sales' => 0.00,
            'nagad_sales' => 0.00,
            'card_bank_sales' => 0.00,
            'other_sales' => 0.00,
        ], $this->user->id);

        $txns = AccountTransaction::where('reference_type', DailySale::class)
            ->where('reference_id', $result['sale']->id)
            ->get();

        // Exactly one transaction for cash drawer, zero rows for other channels
        $this->assertCount(1, $txns);
        $this->assertEquals($this->cashAccount->id, $txns->first()->financial_account_id);
    }
}

