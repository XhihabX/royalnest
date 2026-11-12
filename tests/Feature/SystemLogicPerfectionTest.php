<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DailySale;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FinancialAccountService;
use App\Services\PurchaseService;
use App\Services\StockLedgerService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLogicPerfectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected FinancialAccount $cashDrawer;
    protected FinancialAccount $bkashAccount;
    protected FinancialAccount $bankAccount;
    protected StockLedgerService $stockLedgerService;
    protected PurchaseService $purchaseService;
    protected SupplierService $supplierService;
    protected FinancialAccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Royal Nest Main Branch',
            'code' => 'RN-MAIN-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'email' => 'admin_test_' . uniqid() . '@royalnest.com',
            'primary_role' => 'owner',
        ]);

        $this->cashDrawer = FinancialAccount::create([
            'name' => 'Cash Drawer',
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
            'name' => 'BRAC Bank Current',
            'type' => 'bank',
            'opening_balance' => 50000.00,
            'current_balance' => 50000.00,
            'is_active' => true,
        ]);

        $this->stockLedgerService = app(StockLedgerService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->supplierService = app(SupplierService::class);
        $this->accountService = app(FinancialAccountService::class);
    }

    public function test_wastage_traverses_multiple_fifo_batches_and_calculates_weighted_cost(): void
    {
        $this->actingAs($this->user);

        $category = Category::create([
            'name' => 'Meat & Poultry',
            'slug' => 'meat-poultry-' . uniqid(),
            'type' => 'raw_material',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Beef Tenderloin',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 800.00,
            'current_stock' => 0.00,
            'min_reorder_level' => 2,
        ]);

        // Create Batch 1: 2 kg @ ৳800/kg (received 2 days ago)
        $batch1 = $this->stockLedgerService->createBatch(
            product: $product,
            quantity: 2.0,
            unitCost: 800.00,
            receivedDate: now()->subDays(2)->toDateString(),
            batchNo: 'BATCH-BEEF-01'
        );
        $this->stockLedgerService->recordMovement(
            product: $product,
            movementType: 'purchase_receipt',
            quantityIn: 2.0,
            quantityOut: 0,
            unitCost: 800.00,
            transactionDate: now()->subDays(2)->toDateString(),
            stockBatchId: $batch1->id
        );

        // Create Batch 2: 10 kg @ ৳850/kg (received yesterday)
        $batch2 = $this->stockLedgerService->createBatch(
            product: $product,
            quantity: 10.0,
            unitCost: 850.00,
            receivedDate: now()->subDays(1)->toDateString(),
            batchNo: 'BATCH-BEEF-02'
        );
        $this->stockLedgerService->recordMovement(
            product: $product,
            movementType: 'purchase_receipt',
            quantityIn: 10.0,
            quantityOut: 0,
            unitCost: 850.00,
            transactionDate: now()->subDays(1)->toDateString(),
            stockBatchId: $batch2->id
        );

        $product->refresh();
        $this->assertEquals(12.0, (float)$product->current_stock);

        // Record Wastage of 5 kg (should take 2 kg from Batch 1 and 3 kg from Batch 2)
        // Cost: (2 * 800) + (3 * 850) = 1600 + 2550 = ৳4150.00
        $response = $this->post('/wastage', [
            'product_id' => $product->id,
            'wastage_date' => now()->toDateString(),
            'quantity' => 5.0,
            'reason' => 'spoiled',
            'notes' => 'Freezer temperature malfunction overnight',
        ]);

        $response->assertSessionHasNoErrors();

        $product->refresh();
        $batch1->refresh();
        $batch2->refresh();

        // 1. Product stock correctly reduced to 7 kg
        $this->assertEquals(7.0, (float)$product->current_stock);

        // 2. Batch 1 completely exhausted
        $this->assertEquals(0.0, (float)$batch1->remaining_quantity);
        $this->assertTrue((bool)$batch1->is_exhausted);

        // 3. Batch 2 partially consumed: 10 - 3 = 7 kg remaining
        $this->assertEquals(7.0, (float)$batch2->remaining_quantity);
        $this->assertFalse((bool)$batch2->is_exhausted);

        // 4. Wastage record verified with weighted cost
        $wastage = \App\Models\Wastage::where('product_id', $product->id)->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(4150.00, (float)$wastage->total_cost);
        $this->assertEquals(830.00, (float)$wastage->unit_cost);
    }

    public function test_stock_count_surplus_creates_batch_and_deficit_depletes_fifo_batches(): void
    {
        $this->actingAs($this->user);

        $category = Category::create([
            'name' => 'Grains',
            'slug' => 'grains-' . uniqid(),
            'type' => 'raw_material',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Basmati Rice',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'cost_price' => 120.00,
            'current_stock' => 10.00,
            'min_reorder_level' => 5,
        ]);

        // Active batch of 10 kg
        $batch1 = $this->stockLedgerService->createBatch(
            product: $product,
            quantity: 10.0,
            unitCost: 120.00,
            receivedDate: now()->subDays(5)->toDateString(),
            batchNo: 'BATCH-RICE-01'
        );

        // 1. Test Surplus: Physical count says 14 kg (surplus of +4 kg)
        $responseSurplus = $this->post('/stock-counts', [
            'count_date' => now()->toDateString(),
            'notes' => 'Month-end physical count',
            'items' => [
                [
                    'product_id' => $product->id,
                    'counted_quantity' => 14.0,
                    'adjustment_reason' => 'Found unrecorded sack in dry storage',
                ]
            ]
        ]);

        $responseSurplus->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertEquals(14.0, (float)$product->current_stock);

        // Verify surplus created a dedicated StockBatch
        $surplusBatch = StockBatch::where('product_id', $product->id)
            ->where('batch_no', 'like', 'COUNT-ADJ-%')
            ->first();
        $this->assertNotNull($surplusBatch);
        $this->assertEquals(4.0, (float)$surplusBatch->remaining_quantity);
        $this->assertEquals(120.00, (float)$surplusBatch->unit_cost);

        // 2. Test Deficit: Next count says 8 kg (-6 kg deficit against 14 kg)
        $responseDeficit = $this->post('/stock-counts', [
            'count_date' => now()->toDateString(),
            'notes' => 'Re-audit verification',
            'items' => [
                [
                    'product_id' => $product->id,
                    'counted_quantity' => 8.0,
                    'adjustment_reason' => 'Water damage spillage',
                ]
            ]
        ]);

        $responseDeficit->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertEquals(8.0, (float)$product->current_stock);

        // Batch 1 (oldest) had 10 kg, should now have 10 - 6 = 4 kg remaining
        $batch1->refresh();
        $this->assertEquals(4.0, (float)$batch1->remaining_quantity);
    }

    public function test_daily_sales_intake_deposits_into_cash_and_bank_accounts_and_is_idempotent(): void
    {
        $this->actingAs($this->user);

        $salesDate = now()->toDateString();

        // 1. Submit Daily Sales
        $response = $this->post('/sales', [
            'sales_date' => $salesDate,
            'total_sales' => 30000.00,
            'cash_sales' => 20000.00,
            'bkash_sales' => 5000.00,
            'nagad_sales' => 0.00,
            'card_bank_sales' => 5000.00,
            'notes' => 'Friday busy dinner rush',
        ]);

        $response->assertSessionHasNoErrors();

        $this->cashDrawer->refresh();
        $this->bkashAccount->refresh();
        $this->bankAccount->refresh();

        // Cash Drawer: 10,000 + 20,000 = 30,000
        $this->assertEquals(30000.00, (float)$this->cashDrawer->current_balance);
        // bKash: 5,000 + 5,000 = 10,000
        $this->assertEquals(10000.00, (float)$this->bkashAccount->current_balance);
        // Bank: 50,000 + 5,000 = 55,000
        $this->assertEquals(55000.00, (float)$this->bankAccount->current_balance);

        // 2. Re-submit / edit sales on same date with cash adjusted to ৳25,000 (total ৳35,000)
        // Must adjust cleanly without doubling
        $responseUpdate = $this->post('/sales', [
            'sales_date' => $salesDate,
            'total_sales' => 35000.00,
            'cash_sales' => 25000.00,
            'bkash_sales' => 5000.00,
            'nagad_sales' => 0.00,
            'card_bank_sales' => 5000.00,
            'notes' => 'Corrected cash drawer count',
        ]);

        $responseUpdate->assertSessionHasNoErrors();

        $this->cashDrawer->refresh();
        $this->bkashAccount->refresh();
        $this->bankAccount->refresh();

        // Cash Drawer: 10,000 + 25,000 = 35,000 (NOT 50,000!)
        $this->assertEquals(35000.00, (float)$this->cashDrawer->current_balance);
        $this->assertEquals(10000.00, (float)$this->bkashAccount->current_balance);
        $this->assertEquals(55000.00, (float)$this->bankAccount->current_balance);
    }

    public function test_supplier_statement_allocates_subsequent_payments_across_bills_chronologically(): void
    {
        $supplier = $this->supplierService->createDirectorySupplier([
            'name' => 'Bengal Dairy & Provisions',
            'phone' => '01700112233',
            'status' => 'active',
            'opening_balance' => 0.00,
        ]);

        $category = Category::create([
            'name' => 'Dairy',
            'slug' => 'dairy-' . uniqid(),
            'type' => 'raw_material',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Pure Milk',
            'base_unit' => 'L',
            'purchase_unit' => 'L',
            'cost_price' => 90.00,
            'current_stock' => 0,
            'min_reorder_level' => 10,
        ]);

        // Purchase Bill 1: 100 L @ ৳90 = ৳9,000. Paid at delivery: ৳2,000. Unpaid: ৳7,000.
        $this->purchaseService->confirmPurchase(
            [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-MILK-01',
                'purchase_date' => now()->subDays(3)->toDateString(),
                'paid_amount' => 2000.00,
                'payment_account_id' => $this->cashDrawer->id,
            ],
            [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 100,
                    'purchase_unit' => 'L',
                    'unit_price' => 90.00,
                ]
            ],
            $this->user->id
        );

        // Purchase Bill 2: 50 L @ ৳90 = ৳4,500. Paid at delivery: ৳0. Unpaid: ৳4,500.
        $this->purchaseService->confirmPurchase(
            [
                'supplier_id' => $supplier->id,
                'invoice_no' => 'INV-MILK-02',
                'purchase_date' => now()->subDays(1)->toDateString(),
                'paid_amount' => 0.00,
            ],
            [
                [
                    'product_id' => $product->id,
                    'purchase_quantity' => 50,
                    'purchase_unit' => 'L',
                    'unit_price' => 90.00,
                ]
            ],
            $this->user->id
        );

        $supplier->refresh();
        // Total due = 7,000 + 4,500 = 11,500
        $this->assertEquals(11500.00, (float)$supplier->current_balance);

        // Pay ৳9,000 due payment voucher
        // Should fully settle Bill 1 (remaining ৳7,000) and partially pay Bill 2 (৳2,000 of ৳4,500)
        $this->supplierService->paySupplierDue(
            supplierId: $supplier->id,
            amount: 9000.00,
            paymentDate: now()->toDateString(),
            paymentAccountId: $this->cashDrawer->id,
            paymentMethod: 'cash',
            notes: 'Settling weekly milk bills',
            userId: $this->user->id
        );

        $supplier->refresh();
        // Remaining due: 11,500 - 9,000 = 2,500
        $this->assertEquals(2500.00, (float)$supplier->current_balance);

        // Fetch supplier statement
        $statement = $this->supplierService->getSupplierStatement($supplier->id);
        $this->assertEquals(2500.00, (float)$statement['current_balance']);

        // Verify per-bill status in statement rows
        $rows = collect($statement['statement_rows']);

        $bill1Row = $rows->where('reference', 'INV-MILK-01')->first();
        $this->assertNotNull($bill1Row);
        $this->assertEquals('paid', $bill1Row['bill_status']);
        $this->assertEquals(9000.00, (float)$bill1Row['total_bill_paid']);
        $this->assertEquals(0.00, (float)$bill1Row['remaining_bill_due']);

        $bill2Row = $rows->where('reference', 'INV-MILK-02')->first();
        $this->assertNotNull($bill2Row);
        $this->assertEquals('partially_paid', $bill2Row['bill_status']);
        $this->assertEquals(2000.00, (float)$bill2Row['total_bill_paid']);
        $this->assertEquals(2500.00, (float)$bill2Row['remaining_bill_due']);

        // Check running due at end of statement matches 2500
        $lastRow = $rows->last();
        $this->assertEquals(2500.00, (float)$lastRow['running_due']);
    }
}
