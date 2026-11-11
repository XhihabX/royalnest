<?php

namespace Tests\Feature;

use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class WelcomeTest extends TestCase
{
    public function test_welcome_page_renders_cleanly_with_inertia(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('restaurantName')
            ->has('version')
            ->where('restaurantName', 'Royal Nest Restaurant ERP')
            ->where('version', '2.0.0')
        );
    }
}
