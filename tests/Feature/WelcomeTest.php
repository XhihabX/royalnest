<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_cleanly_with_inertia(): void
    {
        $user = User::factory()->create([
            'email' => 'test_'.uniqid().'@royalnest.com',
            'primary_role' => 'owner',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summary')
            ->has('lowStockProducts')
            ->has('accounts')
            ->has('recentMovements')
        );
    }
}
