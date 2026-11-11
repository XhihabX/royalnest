<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ConsumableAllocation;
use App\Models\DailySale;
use App\Models\Employee;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Wastage;
use App\Services\ConsumableAllocationService;
use App\Services\ConsumptionService;
use App\Services\FinancialAccountService;
use App\Services\ProfitLossService;
use App\Services\PurchaseService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PrdMandatoryScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected StockLedgerService $stockService;
    protected PurchaseService $purchaseService;
    protected ConsumptionService $consumptionService;
    protected ConsumableAllocationService $consumableService;
    protected FinancialAccountService $accountService;
    protected ProfitLossService $profitLossService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin_test_' . uniqid() . '@royalnest.com',
            'primary_role' => 'owner',
        ]);

        $this->stockService = app(StockLedgerService::class);
        $this->accountService = app(FinancialAccountService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->consumptionService = app(ConsumptionService::class);
        $this->consumableService = app(ConsumableAllocationService::class);
        $this->profitLossService = app(ProfitLossService::class);
    }

    /**
     * Helper to create a category and product.
     */
    protected function createProduct(string $name, string $baseUnit, string $purchaseUnit, float $multiplier = 1.0, float $initialStock = 0.0, float $costPrice = 0.0, bool $isConsumable = false): Product
    {
        $category = Category::firstOrCreate(['slug' => 'raw-materials'], [
            'name' => 'Raw Materials',
            'type' => 'raw_material',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'product_type' => $isConsumable ? 'consumable' : 'raw_material',
            'base_unit' => $baseUnit,
            'purchase_unit' => $purchaseUnit,
            'current_stock' => 0.0,
            'cost_price' => $costPrice,
            'is_consumable' => $isConsumable,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => $purchaseUnit,
            'multiplier_to_base' => $multiplier,
        ]);

        if ($initialStock > 0) {
            $this->stockService->recordMovement(
                product: $product,
                movementType: 'opening_stock',
                quantityIn: $initialStock,
                quantityOut: 0,
                unitCost: $costPrice,
                transactionDate: now()->toDateString(),
                userId: $this->user->id,
                notes: 'Initial test opening stock'
            );
            $product->refresh();
        }

        return $product;
    }

    /**
     * Scenario 1: Purchase 100g cardamom and consume 100g -> ending stock = 0g.
     */
    public function test_scenario_1_purchase_and_consume_exact_quantity_results_in_zero_stock(): void
    {
        $cardamom = $this->createProduct('Cardamom', 'g', 'g', 1.0, 0.0, 2.50);

        // Purchase 100g
        $this->purchaseService->confirmPurchase([
            'purchase_date' => '2026-11-12',
            'notes' => '100g Cardamom purchase',
        ], [
            [
                'product_id' => $cardamom->id,
                'purchase_quantity' => 100.0,
                'purchase_unit' => 'g',
                'unit_price' => 2.50,
            ]
        ], $this->user->id);

        $cardamom->refresh();
        $this->assertEquals(100.0, (float)$cardamom->current_stock);

        // Consume 100g
        $this->consumptionService->recordConsumption('2026-11-12', [
            [
                'product_id' => $cardamom->id,
                'quantity_used' => 100.0,
            ]
        ], $this->user->id);

        $cardamom->refresh();
        $this->assertEquals(0.0, (float)$cardamom->current_stock);
        $this->assertEquals(2, $cardamom->stockLedgers()->count());
    }

    /**
     * Scenario 2: Purchase 1kg rice (base unit g, 1kg = 1000g) and consume 300g -> ending stock = 700g.
     */
    public function test_scenario_2_purchase_unit_conversion_and_partial_consumption(): void
    {
        $rice = $this->createProduct('Basmati Rice', 'g', 'kg', 1000.0, 0.0, 0.12);

        // Purchase 1 kg
        $this->purchaseService->confirmPurchase([
            'purchase_date' => '2026-11-12',
        ], [
            [
                'product_id' => $rice->id,
                'purchase_quantity' => 1.0, // 1 kg
                'purchase_unit' => 'kg',
                'unit_price' => 120.00, // ৳120 per kg = ৳0.12 per gram
            ]
        ], $this->user->id);

        $rice->refresh();
        $this->assertEquals(1000.0, (float)$rice->current_stock);

        // Consume 300 grams
        $this->consumptionService->recordConsumption('2026-11-12', [
            [
                'product_id' => $rice->id,
                'quantity_used' => 300.0,
            ]
        ], $this->user->id);

        $rice->refresh();
        $this->assertEquals(700.0, (float)$rice->current_stock);
    }

    /**
     * Scenario 3: FIFO (First-In, First-Out) Batch Costing Engine.
     * Previous stock retains its purchase price (10 kg @ ৳100 in Batch 1).
     * Future stock retains its own purchase price (10 kg @ ৳120 in Batch 2).
     * Consuming 5 kg consumes from Batch 1 at ৳100/kg -> cost = ৳500 (NOT blended ৳550!).
     * Consuming another 7 kg spans 5 kg from Batch 1 @ ৳100 + 2 kg from Batch 2 @ ৳120 -> cost = ৳740.
     */
    public function test_scenario_3_fifo_batch_cost_calculation(): void
    {
        $beef = $this->createProduct('Beef', 'kg', 'kg', 1.0, 10.0, 100.00);
        $this->assertEquals(10.0, (float)$beef->current_stock);

        // Purchase another 10 kg @ ৳120 (Batch 2)
        $this->purchaseService->confirmPurchase([
            'purchase_date' => '2026-11-12',
        ], [
            [
                'product_id' => $beef->id,
                'purchase_quantity' => 10.0,
                'purchase_unit' => 'kg',
                'unit_price' => 120.00,
            ]
        ], $this->user->id);

        $beef->refresh();
        $this->assertEquals(20.0, (float)$beef->current_stock);

        // Verify two distinct batches exist
        $batches = $beef->activeBatches()->get();
        $this->assertCount(2, $batches);
        $this->assertEquals(100.00, (float)$batches[0]->unit_cost);
        $this->assertEquals(120.00, (float)$batches[1]->unit_cost);

        // 1. Consume 5 kg: FIFO must take from the older Batch 1 @ ৳100/kg
        $consumption1 = $this->consumptionService->recordConsumption('2026-11-12', [
            [
                'product_id' => $beef->id,
                'quantity_used' => 5.0,
            ]
        ], $this->user->id);

        $beef->refresh();
        $this->assertEquals(15.0, (float)$beef->current_stock);
        // Cost is 5kg * ৳100 = ৳500 (NOT ৳550)
        $this->assertEquals(500.00, (float)$consumption1->total_cost);

        // 2. Consume another 7 kg: takes remaining 5 kg of Batch 1 @ ৳100 + 2 kg of Batch 2 @ ৳120
        $consumption2 = $this->consumptionService->recordConsumption('2026-11-12', [
            [
                'product_id' => $beef->id,
                'quantity_used' => 7.0,
            ]
        ], $this->user->id);

        $beef->refresh();
        $this->assertEquals(8.0, (float)$beef->current_stock);
        // Cost is (5 * 100) + (2 * 120) = 500 + 240 = ৳740
        $this->assertEquals(740.00, (float)$consumption2->total_cost);

        // Batch 1 is exhausted; Batch 2 has 8 kg remaining
        $this->assertCount(1, $beef->activeBatches()->get());
        $remainingBatch = $beef->activeBatches()->first();
        $this->assertEquals(8.0, (float)$remainingBatch->remaining_quantity);
        $this->assertEquals(120.00, (float)$remainingBatch->unit_cost);
    }

    /**
     * Scenario 4: Negative Stock Prevention (PRD Section 13).
     * By default, do not allow confirmed consumption to make stock negative.
     */
    public function test_scenario_4_negative_stock_prevention(): void
    {
        $chicken = $this->createProduct('Broiler Chicken', 'kg', 'kg', 1.0, 5.0, 180.00);

        $this->expectException(ValidationException::class);

        // Attempt to consume 10kg when only 5kg is available
        $this->consumptionService->recordConsumption('2026-11-12', [
            [
                'product_id' => $chicken->id,
                'quantity_used' => 10.0,
            ]
        ], $this->user->id);
    }

    /**
     * Scenario 5: Wastage reduces stock and is recognised as an operating cost (PRD Section 9).
     */
    public function test_scenario_5_wastage_tracking_and_costing(): void
    {
        $milk = $this->createProduct('Liquid Milk', 'L', 'L', 1.0, 10.0, 80.00);

        // Record 2L spoiled milk
        $unitCost = (float)$milk->cost_price;
        $qtyWasted = 2.0;
        $totalWastageCost = round($qtyWasted * $unitCost, 2);

        $wastage = Wastage::create([
            'product_id' => $milk->id,
            'wastage_date' => '2026-11-12',
            'quantity' => $qtyWasted,
            'unit_cost' => $unitCost,
            'total_cost' => $totalWastageCost,
            'reason' => 'spoiled',
            'notes' => 'Milk spoiled due to refrigerator breakdown',
            'created_by' => $this->user->id,
        ]);

        $this->stockService->recordMovement(
            product: $milk,
            movementType: 'wastage',
            quantityIn: 0,
            quantityOut: $qtyWasted,
            unitCost: $unitCost,
            transactionDate: '2026-11-12',
            referenceType: Wastage::class,
            referenceId: $wastage->id,
            userId: $this->user->id,
            notes: 'Spoiled milk wastage'
        );

        $milk->refresh();
        $this->assertEquals(8.0, (float)$milk->current_stock);

        // Verify P&L reflects wastage
        $pnl = $this->profitLossService->getDailySummary('2026-11-12');
        $this->assertEquals(160.00, (float)$pnl['operating_costs']['wastage_cost']);
    }

    /**
     * Scenario 6: Physical Stock Count Difference Adjustments (PRD Section 10).
     */
    public function test_scenario_6_physical_stock_count_difference(): void
    {
        $sugar = $this->createProduct('Sugar', 'kg', 'kg', 1.0, 20.0, 130.00);

        // Stock count reveals 18 kg counted instead of 20 kg (2 kg missing)
        $count = StockCount::create([
            'count_date' => '2026-11-12',
            'status' => 'confirmed',
            'notes' => 'Weekly physical inventory count',
            'created_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $diffQty = -2.0;
        StockCountItem::create([
            'stock_count_id' => $count->id,
            'product_id' => $sugar->id,
            'system_quantity' => 20.0,
            'counted_quantity' => 18.0,
            'difference_quantity' => $diffQty,
            'unit_cost' => 130.00,
            'difference_value' => round($diffQty * 130.00, 2),
            'adjustment_reason' => 'Spillage during handling',
        ]);

        $this->stockService->recordMovement(
            product: $sugar,
            movementType: 'adjustment_out',
            quantityIn: 0,
            quantityOut: 2.0,
            unitCost: 130.00,
            transactionDate: '2026-11-12',
            referenceType: StockCount::class,
            referenceId: $count->id,
            userId: $this->user->id,
            notes: 'Physical count adjustment (-2kg)'
        );

        $sugar->refresh();
        $this->assertEquals(18.0, (float)$sugar->current_stock);
    }

    /**
     * Scenario 7: Consumables Cost Allocation (PRD Section 14).
     * Tissue purchase cost ৳120, expected 4 days -> ৳30/day daily allocation.
     * Does NOT charge ৳120 to daily P&L on day 1.
     */
    public function test_scenario_7_consumables_multi_day_allocation(): void
    {
        $tissue = $this->createProduct('Tissue Box', 'pcs', 'pcs', 1.0, 10.0, 120.00, true);

        // Create 4-day allocation for ৳120 starting 2026-11-10 to 2026-11-13
        $allocation = $this->consumableService->createAllocation(
            productId: $tissue->id,
            purchaseId: null,
            startDate: '2026-11-10',
            durationDays: 4,
            totalCost: 120.00,
            notes: 'Dining room tissue allocation'
        );

        $this->assertEquals(30.00, (float)$allocation->daily_allocated_cost);
        $this->assertEquals('2026-11-13', $allocation->end_date->toDateString());

        // Check daily allocated cost for 2026-11-11
        $dailyCost = $this->consumableService->getAllocatedCostForDate('2026-11-11');
        $this->assertEquals(30.00, $dailyCost);

        // Check P&L on 2026-11-11
        $pnl = $this->profitLossService->getDailySummary('2026-11-11');
        $this->assertEquals(30.00, (float)$pnl['cost_of_materials']['consumables_allocated']);
    }

    /**
     * Scenario 8: Employee Salary Cost vs Cash Payments (PRD Section 17).
     * Monthly salary cost: ৳15,000. Paid: ৳10,000. Remaining payable: ৳5,000.
     * Never count payment as a duplicate expense.
     */
    public function test_scenario_8_salary_cost_accrual_vs_cash_payment(): void
    {
        $cashDrawer = FinancialAccount::create([
            'name' => 'Cash Drawer',
            'type' => 'cash',
            'opening_balance' => 20000.00,
            'current_balance' => 20000.00,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'name' => 'Karim Ali',
            'role' => 'Waiter',
            'joining_date' => '2026-01-01',
            'salary_type' => 'monthly',
            'agreed_salary' => 15000.00,
            'is_active' => true,
        ]);

        // Monthly salary is ৳15,000. In November (30 days), daily accrual is ৳15,000 / 30 = ৳500/day.
        $pnl = $this->profitLossService->getDailySummary('2026-11-12');
        $this->assertEquals(500.00, (float)$pnl['operating_costs']['salary_cost']);

        // Cash payment of ৳10,000 advance/partial salary
        $this->accountService->withdraw(
            accountId: $cashDrawer->id,
            amount: 10000.00,
            date: '2026-11-12',
            referenceType: Employee::class,
            referenceId: $employee->id,
            userId: $this->user->id,
            notes: 'Partial salary payment'
        );

        $cashDrawer->refresh();
        $this->assertEquals(10000.00, (float)$cashDrawer->current_balance);

        // Outstanding payable is ৳5,000
        $totalPaid = 10000.00;
        $remainingPayable = (float)$employee->agreed_salary - $totalPaid;
        $this->assertEquals(5000.00, $remainingPayable);

        // Daily P&L salary cost is STILL ৳500/day, NOT ৳10,500!
        $pnlAfterPayment = $this->profitLossService->getDailySummary('2026-11-12');
        $this->assertEquals(500.00, (float)$pnlAfterPayment['operating_costs']['salary_cost']);
    }

    /**
     * Scenario 9: Internal Account Transfer has ZERO effect on Profit & Loss (PRD Section 18).
     */
    public function test_scenario_9_internal_account_transfers_do_not_affect_profit_loss(): void
    {
        $cash = FinancialAccount::create([
            'name' => 'Cash Drawer',
            'type' => 'cash',
            'current_balance' => 15000.00,
        ]);

        $bank = FinancialAccount::create([
            'name' => 'BRAC Bank',
            'type' => 'bank',
            'current_balance' => 50000.00,
        ]);

        // Record a sale for the day to establish a baseline profit
        DailySale::create([
            'sales_date' => '2026-11-12',
            'total_sales' => 10000.00,
            'cash_sales' => 10000.00,
            'created_by' => $this->user->id,
        ]);

        $pnlBefore = $this->profitLossService->getDailySummary('2026-11-12');

        // Transfer ৳5,000 from Cash Drawer to BRAC Bank
        $this->accountService->transfer(
            fromAccountId: $cash->id,
            toAccountId: $bank->id,
            amount: 5000.00,
            date: '2026-11-12',
            userId: $this->user->id,
            notes: 'Bank deposit of excess cash'
        );

        $cash->refresh();
        $bank->refresh();
        $this->assertEquals(10000.00, (float)$cash->current_balance);
        $this->assertEquals(55000.00, (float)$bank->current_balance);

        $pnlAfter = $this->profitLossService->getDailySummary('2026-11-12');

        // Profit must remain IDENTICAL before and after the transfer
        $this->assertEquals($pnlBefore['net_profit'], $pnlAfter['net_profit']);
    }

    /**
     * Scenario 10: Inventory Purchase without Consumption does not hit P&L (PRD Section 12).
     */
    public function test_scenario_10_inventory_purchase_does_not_hit_pnl_until_consumed(): void
    {
        $oil = $this->createProduct('Mustard Oil', 'ml', 'L', 1000.0, 0.0, 0.22);

        // Record ৳5,000 sales
        DailySale::create([
            'sales_date' => '2026-11-12',
            'total_sales' => 5000.00,
            'cash_sales' => 5000.00,
            'created_by' => $this->user->id,
        ]);

        $pnlBefore = $this->profitLossService->getDailySummary('2026-11-12');

        // Purchase 10 Litres of Oil for ৳2,200 (stored in inventory, not consumed yet)
        $this->purchaseService->confirmPurchase([
            'purchase_date' => '2026-11-12',
        ], [
            [
                'product_id' => $oil->id,
                'purchase_quantity' => 10.0,
                'purchase_unit' => 'L',
                'unit_price' => 220.00,
            ]
        ], $this->user->id);

        $oil->refresh();
        $this->assertEquals(10000.0, (float)$oil->current_stock); // 10,000 ml

        $pnlAfter = $this->profitLossService->getDailySummary('2026-11-12');

        // Material cost in P&L must remain 0 since none was consumed!
        $this->assertEquals(0.00, (float)$pnlAfter['cost_of_materials']['ingredient_consumption']);
        $this->assertEquals($pnlBefore['net_profit'], $pnlAfter['net_profit']);
    }

    /**
     * Scenario 11: Direct "Purchase & Consume" shortcut (PRD Section 6).
     * Purchases 100g Cardamom and consumes it same day:
     * - Stock increases then decreases (ending stock = 0g).
     * - Both movements recorded in stock ledger.
     * - Recognized once as consumption cost, zero duplicate expense.
     */
    public function test_scenario_11_direct_purchase_and_consume_workflow(): void
    {
        $cardamom = $this->createProduct('Green Cardamom', 'g', 'g', 1.0, 0.0, 2.50);

        $purchase = $this->purchaseService->confirmPurchase([
            'purchase_date' => '2026-11-12',
            'is_purchase_and_consume' => true,
            'notes' => 'Daily special cooking cardamom',
        ], [
            [
                'product_id' => $cardamom->id,
                'purchase_quantity' => 100.0,
                'purchase_unit' => 'g',
                'unit_price' => 2.50,
            ]
        ], $this->user->id);

        $cardamom->refresh();

        // 1. Ending stock is 0
        $this->assertEquals(0.0, (float)$cardamom->current_stock);

        // 2. Both movements recorded in stock ledger (+100 and -100)
        $ledgers = $cardamom->stockLedgers()->orderBy('id')->get();
        $this->assertCount(2, $ledgers);
        $this->assertEquals('purchase_receipt', $ledgers[0]->movement_type);
        $this->assertEquals(100.0, (float)$ledgers[0]->quantity_in);
        $this->assertEquals('consumption', $ledgers[1]->movement_type);
        $this->assertEquals(100.0, (float)$ledgers[1]->quantity_out);

        // 3. P&L recognizes consumption cost once (৳250)
        $pnl = $this->profitLossService->getDailySummary('2026-11-12');
        $this->assertEquals(250.00, (float)$pnl['cost_of_materials']['ingredient_consumption']);
    }

    /**
     * Scenario 12: Daily Sales Discrepancy Tracking (PRD Section 15).
     */
    public function test_scenario_12_daily_sales_discrepancy_tracking(): void
    {
        $sale = DailySale::create([
            'sales_date' => '2026-11-12',
            'total_sales' => 10000.00,
            'cash_sales' => 5000.00,
            'bkash_sales' => 3000.00,
            'nagad_sales' => 1500.00,
            'card_bank_sales' => 0.00,
            'discrepancy_amount' => 500.00, // 5000+3000+1500 = 9500 vs 10000 total sales -> 500 discrepancy
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(10000.00, (float)$sale->total_sales);
        $this->assertEquals(500.00, (float)$sale->discrepancy_amount);

        $pnl = $this->profitLossService->getDailySummary('2026-11-12');
        $this->assertEquals(10000.00, (float)$pnl['sales']['total_sales']);
        $this->assertEquals(500.00, (float)$pnl['sales']['discrepancy']);
    }
}

