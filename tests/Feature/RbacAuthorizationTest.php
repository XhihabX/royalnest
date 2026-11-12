<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_have_access_to_all_modules(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('sales.index'))->assertOk();
        $this->get(route('products.index'))->assertOk();
        $this->get(route('accounts.index'))->assertOk();
        $this->get(route('reports.profit-loss'))->assertOk();
    }

    public function test_cashier_can_access_sales_but_blocked_from_pnl_and_accounts(): void
    {
        $cashier = User::factory()->create([
            'primary_role' => 'cashier',
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        // Permitted
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('sales.index'))->assertOk();
        $this->get(route('consumption.index'))->assertOk();

        // Forbidden
        $this->get(route('accounts.index'))->assertForbidden();
        $this->get(route('reports.profit-loss'))->assertForbidden();
        $this->get(route('products.index'))->assertForbidden();
        $this->get(route('employees.index'))->assertForbidden();
    }

    public function test_deactivated_user_is_logged_out_and_redirected_to_login(): void
    {
        $user = User::factory()->create([
            'primary_role' => 'cashier',
            'is_active' => false,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('sales.index'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
