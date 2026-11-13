<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Consumption;
use App\Models\DailySale;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\FinancialAccount;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SalaryPayment;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FinancialAccountService;
use App\Services\PurchaseService;
use App\Services\ReversalService;
use App\Services\StockLedgerService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class SystemWideEnhancementAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $visitor;
    protected Branch $branch;
    protected FinancialAccount $cashAccount;
    protected ReversalService $reversalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Royal Nest Main Branch',
            'code' => 'RN-01',
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'branch_id' => $this->branch->id,
            'name' => 'Royal Nest Admin',
            'email' => 'admin@royalnest.com',
            'phone' => '01711112222',
            'primary_role' => 'owner',
            'is_super_admin' => true,
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        $this->manager = User::create([
            'branch_id' => $this->branch->id,
            'name' => 'General Manager',
            'email' => 'manager@royalnest.com',
            'phone' => '01733334444',
            'primary_role' => 'manager',
            'is_super_admin' => false,
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        $this->visitor = User::create([
            'branch_id' => $this->branch->id,
            'name' => 'Auditor Guest',
            'email' => 'visitor@royalnest.com',
            'phone' => '01755556666',
            'primary_role' => 'visitor',
            'is_super_admin' => false,
            'is_active' => true,
            'password' => bcrypt('password123'),
        ]);

        $this->cashAccount = FinancialAccount::create([
            'name' => 'Main Cash Drawer',
            'type' => 'cash',
            'current_balance' => 50000.00,
            'is_active' => true,
        ]);

        $this->reversalService = app(ReversalService::class);
    }

    /**
     * 1. Test Super Admin Protection: cannot be deleted, deactivated, or demoted.
     */
    public function test_super_admin_cannot_be_deleted_deactivated_or_demoted(): void
    {
        $this->actingAs($this->superAdmin);

        // Attempting to delete Super Admin returns error
        $response = $this->delete("/users/{$this->superAdmin->id}");
        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);

        // Attempting to deactivate Super Admin returns error
        $toggleResp = $this->post("/users/{$this->superAdmin->id}/toggle-status");
        $toggleResp->assertSessionHasErrors('user');
        $this->assertTrue((bool) $this->superAdmin->fresh()->is_active);

        // Attempting to demote Super Admin role
        $demoteResp = $this->put("/users/{$this->superAdmin->id}", [
            'name' => 'Royal Nest Admin',
            'email' => 'admin_updated@royalnest.com',
            'phone' => '01711112222',
            'primary_role' => 'cashier', // Demotion attempt
        ]);
        $demoteResp->assertSessionHasErrors('primary_role');
        $this->assertEquals('owner', $this->superAdmin->fresh()->primary_role);

        // Direct model delete throws AccessDeniedHttpException
        $this->expectException(AccessDeniedHttpException::class);
        $this->superAdmin->delete();
    }

    /**
     * 2. Test Visitor Account: cannot modify state (403), but can safely logout.
     */
    public function test_visitor_account_cannot_execute_state_changing_actions_but_can_logout(): void
    {
        $this->actingAs($this->visitor);

        // Attempting to create an expense as visitor is blocked with HTTP 403
        $expenseResp = $this->post('/expenses', [
            'expense_date' => now()->toDateString(),
            'category' => 'cleaning',
            'amount' => 500,
            'payment_account_id' => $this->cashAccount->id,
        ]);
        $expenseResp->assertStatus(403);
        $this->assertDatabaseMissing('operating_expenses', ['amount' => 500]);

        // Safe session operation (logout) is permitted for visitors
        $logoutResp = $this->post('/logout');
        $logoutResp->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * 3. Test Activity Log: records events and rejects modification or deletion.
     */
    public function test_audit_log_captures_events_and_rejects_deletion_or_modification(): void
    {
        $this->actingAs($this->superAdmin);

        $log = AuditLogger::log(
            action: 'created',
            description: 'Manual test entry for activity log',
            entity: null,
            module: 'system',
            transactionReference: 'TEST-REF-001'
        );

        $this->assertNotNull($log);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'module' => 'system',
            'transaction_reference' => 'TEST-REF-001',
            'status' => 'success',
        ]);

        // Attempting to update or delete the audit record throws AccessDeniedHttpException
        try {
            $log->update(['description' => 'Tampered description']);
            $this->fail('Audit log update should have thrown AccessDeniedHttpException');
        } catch (AccessDeniedHttpException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Audit log delete should have thrown AccessDeniedHttpException');
        } catch (AccessDeniedHttpException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    /**
     * 4. Test Purchase Reversal Matrix: clean intact batch reversal vs consumed batch blocking.
     */
    public function test_purchase_reversal_safely_handles_intact_vs_consumed_fifo_batches(): void
    {
        $this->actingAs($this->superAdmin);

        $category = Category::create([
            'name' => 'Test Ingredients',
            'slug' => 'test-ingredients',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Metro Agrovita',
            'company_name' => 'Metro Agrovita',
            'contact_person' => 'Jamil',
            'phone' => '01899990000',
            'category' => 'Produce',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Basmati Rice Lot',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 0,
            'cost_price' => 120.00,
            'is_active' => true,
        ]);

        ProductUnitConversion::create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'multiplier_to_base' => 1.0,
        ]);

        // A. Create Purchase with immediate cash payment
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-TEST-001',
            'purchase_date' => now()->toDateString(),
            'total_amount' => 1200.00,
            'paid_amount' => 1200.00,
            'status' => 'paid',
            'reversal_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'purchase_quantity' => 10.0,
            'purchase_unit' => 'kg',
            'base_quantity' => 10.0,
            'base_unit_cost' => 120.00,
            'unit_price' => 120.00,
            'subtotal' => 1200.00,
        ]);

        $batch = StockBatch::create([
            'purchase_item_id' => $item->id,
            'product_id' => $product->id,
            'batch_no' => 'BATCH-TEST-001',
            'received_date' => now()->toDateString(),
            'initial_quantity' => 10.0,
            'remaining_quantity' => 10.0,
            'unit_cost' => 120.00,
            'is_exhausted' => false,
        ]);

        $product->update(['current_stock' => 10.0]);
        $this->cashAccount->update(['current_balance' => 50000.00 - 1200.00]);

        // Case 1: Stock is 100% intact -> Clean Void succeeds
        $result = $this->reversalService->voidPurchase($purchase, 'Typo duplicate entry');
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $product->fresh()->current_stock);
        $this->assertEquals('voided', $purchase->fresh()->reversal_status);
        $this->assertEquals(50000.00, $this->cashAccount->fresh()->current_balance);

        // Case 2: Stock partially consumed -> Full void without partial flag is BLOCKED
        $purchase2 = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-TEST-002',
            'purchase_date' => now()->toDateString(),
            'total_amount' => 1200.00,
            'paid_amount' => 1200.00,
            'status' => 'paid',
            'reversal_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $item2 = PurchaseItem::create([
            'purchase_id' => $purchase2->id,
            'product_id' => $product->id,
            'purchase_quantity' => 10.0,
            'purchase_unit' => 'kg',
            'base_quantity' => 10.0,
            'base_unit_cost' => 120.00,
            'unit_price' => 120.00,
            'subtotal' => 1200.00,
        ]);

        $batch2 = StockBatch::create([
            'purchase_item_id' => $item2->id,
            'product_id' => $product->id,
            'batch_no' => 'BATCH-TEST-002',
            'received_date' => now()->toDateString(),
            'initial_quantity' => 10.0,
            'remaining_quantity' => 6.0, // 4 kg consumed in kitchen!
            'unit_cost' => 120.00,
            'is_exhausted' => false,
        ]);
        $product->update(['current_stock' => 6.0]);

        $this->expectException(ValidationException::class);
        $this->reversalService->voidPurchase($purchase2, 'Mistake', false);
    }

    /**
     * 5. Test Supplier Payment Reversal: restores balance and unwinds bill payment status.
     */
    public function test_supplier_payment_reversal_recredits_balance_and_restores_bill_status(): void
    {
        $this->actingAs($this->superAdmin);

        $supplier = Supplier::create([
            'name' => 'Farm Fresh Poultry',
            'company_name' => 'Farm Fresh Poultry',
            'phone' => '01788887777',
            'status' => 'active',
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_no' => 'INV-CHICKEN-01',
            'purchase_date' => now()->toDateString(),
            'total_amount' => 5000.00,
            'paid_amount' => 5000.00,
            'status' => 'paid',
            'created_by' => $this->superAdmin->id,
        ]);

        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000.00,
            'payment_account_id' => $this->cashAccount->id,
            'voucher_no' => 'VOUCH-TEST-01',
            'payment_method' => 'cash',
            'reversal_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->cashAccount->update(['current_balance' => 45000.00]);

        // Void the mistaken payment voucher
        $this->reversalService->voidSupplierPayment($payment, 'Voucher entered twice');

        $this->assertEquals('voided', $payment->fresh()->reversal_status);
        $this->assertEquals(5000.00, (float) $supplier->fresh()->current_balance);
        $this->assertEquals(50000.00, (float) $this->cashAccount->fresh()->current_balance);
    }

    /**
     * 6. Test Salary Payment and Staff Advance Reversals.
     */
    public function test_salary_payment_and_advance_reversal_synchronizes_financial_accounts(): void
    {
        $this->actingAs($this->superAdmin);

        $employee = Employee::create([
            'employee_id' => 'EMP-TEST-01',
            'name' => 'Chef Masud',
            'role' => 'Head Chef',
            'salary_type' => 'monthly',
            'agreed_salary' => 15000.00,
            'joining_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $salaryPayment = SalaryPayment::create([
            'employee_id' => $employee->id,
            'payment_date' => now()->toDateString(),
            'payment_type' => 'salary_payment',
            'amount' => 5000.00,
            'payment_account_id' => $this->cashAccount->id,
            'month_year' => now()->format('Y-m'),
            'reversal_status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->cashAccount->update(['current_balance' => 45000.00]);

        // Void salary payment
        $this->reversalService->voidSalaryPayment($salaryPayment, 'Accidental duplicate payment click');

        $this->assertEquals('voided', $salaryPayment->fresh()->reversal_status);
        $this->assertEquals(50000.00, (float) $this->cashAccount->fresh()->current_balance);
    }

    /**
     * 7. Test Recycle Bin: restores soft-deleted records and blocks permanent purge of used entities.
     */
    public function test_recycle_bin_restores_records_and_blocks_permanent_deletion_of_used_entities(): void
    {
        $this->actingAs($this->superAdmin);

        $category = Category::create([
            'name' => 'Disposable Boxes',
            'slug' => 'disposable-boxes',
            'type' => 'packaging',
            'is_active' => true,
        ]);

        // Soft delete category
        $category->delete();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        // Restore via RecycleBinController
        $resp = $this->post('/recycle-bin/restore', [
            'type' => 'category',
            'id' => $category->id,
        ]);
        $resp->assertRedirect();
        $this->assertNull($category->fresh()->deleted_at);
    }

    /**
     * 8. Test Daily Cost Summary: strictly separates COGS, Expenses, Outflows, and Purchases.
     */
    public function test_daily_cost_summary_strictly_separates_cogs_expenses_and_outflows(): void
    {
        $this->actingAs($this->superAdmin);
        $today = now()->toDateString();

        OperatingExpense::create([
            'expense_date' => $today,
            'category' => 'gas',
            'amount' => 1500.00,
            'payment_account_id' => $this->cashAccount->id,
            'created_by' => $this->superAdmin->id,
        ]);

        DailySale::create([
            'sales_date' => $today,
            'total_sales' => 12000.00,
            'cash_sales' => 8000.00,
            'bkash_sales' => 4000.00,
            'created_by' => $this->superAdmin->id,
        ]);

        $resp = $this->get("/daily-costs?date={$today}");
        $resp->assertOk();
        $resp->assertInertia(fn ($page) => $page
            ->component('Costs/Index')
            ->has('dailySummary')
            ->where('dailySummary.operating_expenses', 1500)
            ->where('dailySummary.gross_sales', 12000)
            ->where('dailySummary.cash_sales', 8000)
        );
    }

    /**
     * 9. Test Voice Lookup API: returns matching database entities for voice parser.
     */
    public function test_voice_lookup_api_returns_fresh_matching_database_entities(): void
    {
        $this->actingAs($this->superAdmin);

        $category = Category::create([
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'type' => 'raw_material',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Fresh Onion (পেঁয়াজ)',
            'product_type' => 'raw_material',
            'base_unit' => 'kg',
            'purchase_unit' => 'kg',
            'current_stock' => 50,
            'cost_price' => 80.00,
            'is_active' => true,
        ]);

        $resp = $this->getJson('/api/voice/lookup?type=products&q=Onion');
        $resp->assertOk();
        $resp->assertJsonPath('success', true);
        $resp->assertJsonFragment(['name' => 'Fresh Onion (পেঁয়াজ)']);
    }
}
