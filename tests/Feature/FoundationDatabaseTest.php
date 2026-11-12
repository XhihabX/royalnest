<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RoyalNestV1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoyalNestV1Seeder::class);
    }

    public function test_branch_and_roles_exist(): void
    {
        $branch = Branch::where('code', 'MAIN-01')->first();
        $this->assertNotNull($branch);
        $this->assertEquals('Royal Nest Café & Restaurant', $branch->name);
        $this->assertEquals('BDT', $branch->currency);
        $this->assertEquals('৳', $branch->currency_symbol);

        $this->assertTrue(Role::where('name', 'owner')->exists());
        $this->assertTrue(Role::where('name', 'manager')->exists());
        $this->assertTrue(Role::where('name', 'cashier')->exists());
    }

    public function test_financial_accounts_are_created(): void
    {
        $cash = FinancialAccount::where('type', 'cash')->first();
        $this->assertNotNull($cash);
        $this->assertEquals('Cash Drawer', $cash->name);

        $bkash = FinancialAccount::where('type', 'bkash')->first();
        $this->assertNotNull($bkash);

        $bank = FinancialAccount::where('type', 'bank')->first();
        $this->assertNotNull($bank);
    }

    public function test_products_and_conversions_exist(): void
    {
        $cardamom = Product::where('name', 'Cardamom (Green Elaichi)')->first();
        $this->assertNotNull($cardamom);
        $this->assertEquals('g', $cardamom->base_unit);
        $this->assertEquals('kg', $cardamom->purchase_unit);
        $this->assertEquals(100.0, (float) $cardamom->current_stock);

        $conv = $cardamom->unitConversions()->first();
        $this->assertNotNull($conv);
        $this->assertEquals(1000.0, (float) $conv->multiplier_to_base);
    }

    public function test_audit_logger_creates_immutable_log(): void
    {
        $admin = User::where('email', 'admin@royalnest.com')->first();
        $this->actingAs($admin);

        $log = AuditLogger::log(
            action: 'test_action',
            description: 'Phase verification audit',
            beforePayload: ['status' => 'pending'],
            afterPayload: ['status' => 'verified']
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'test_action',
        ]);
    }
}
