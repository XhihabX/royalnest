<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuAndInventoryWebTest extends TestCase
{
    public function test_menu_catalog_page_renders_successfully(): void
    {
        $response = $this->get('/menu');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Menu/Index')
            ->has('categories')
            ->has('menuItems')
            ->has('filters')
        );
    }

    public function test_recipe_bom_page_renders_successfully(): void
    {
        $response = $this->get('/recipes');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Recipes/Index')
            ->has('recipes')
        );
    }

    public function test_inventory_ledger_page_renders_successfully(): void
    {
        $response = $this->get('/inventory');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Index')
            ->has('locations')
            ->has('stockItems')
            ->has('recentMovements')
        );
    }
}
