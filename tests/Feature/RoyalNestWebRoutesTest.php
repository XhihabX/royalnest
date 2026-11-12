<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoyalNestWebRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'web_admin_'.uniqid().'@royalnest.com',
            'primary_role' => 'owner',
        ]);
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_all_primary_authenticated_routes_render_successfully(): void
    {
        $this->actingAs($this->user);

        $routes = [
            '/',
            '/sales',
            '/products',
            '/suppliers/directory',
            '/suppliers/active',
            '/purchases',
            '/consumption',
            '/consumables',
            '/ledger',
            '/expenses',
            '/wastage',
            '/stock-counts',
            '/accounts',
            '/employees',
            '/employees/attendance/bulk',
            '/reports/profit-loss',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertStatus(200);
        }
    }
}
