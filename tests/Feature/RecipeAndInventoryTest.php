<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeVersion;
use App\Models\StockItem;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\RecipeCostingService;
use App\Services\StockMovementService;
use App\Services\UnitConversionService;
use InvalidArgumentException;
use Tests\TestCase;

class RecipeAndInventoryTest extends TestCase
{
    protected UnitConversionService $unitConverter;
    protected RecipeCostingService $costingService;
    protected StockMovementService $movementService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unitConverter = app(UnitConversionService::class);
        $this->costingService = app(RecipeCostingService::class);
        $this->movementService = app(StockMovementService::class);
    }

    public function test_unit_conversion_service_precision(): void
    {
        $kg = Unit::where('symbol', 'kg')->firstOrFail();
        $g  = Unit::where('symbol', 'g')->firstOrFail();
        $l  = Unit::where('symbol', 'L')->firstOrFail();

        // 1.5 kg to grams
        $grams = $this->unitConverter->convert(1.5, $kg, $g);
        $this->assertEquals(1500.0, $grams);

        // 250 g to kilograms (inverse)
        $kilos = $this->unitConverter->convert(250, $g, $kg);
        $this->assertEquals(0.25, $kilos);

        // Incompatible units throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->unitConverter->convert(1.0, $kg, $l);
    }

    public function test_stock_movement_weighted_average_cost_calculation(): void
    {
        $branch = Branch::where('code', 'MAIN-01')->firstOrFail();
        $location = StockLocation::where('code', 'LOC-MAIN')->firstOrFail();
        $kg = Unit::where('symbol', 'kg')->firstOrFail();

        // Create a test stock item with 0 stock
        $item = StockItem::create([
            'branch_id' => $branch->id,
            'code' => 'TEST-BEEF-' . uniqid(),
            'name' => 'Test Premium Beef',
            'category' => 'Meat & Poultry',
            'purchase_unit_id' => $kg->id,
            'stock_unit_id' => $kg->id,
            'recipe_unit_id' => $kg->id,
            'cost_price' => 0.0000,
            'current_stock' => 0.0000,
        ]);

        // First purchase: 50 kg @ ৳700 / kg
        $this->movementService->recordMovement(
            $branch->id,
            $location->id,
            $item->id,
            'goods_received',
            50.0,
            $kg->id,
            700.00,
            'test',
            null,
            null,
            'First batch'
        );

        $item->refresh();
        $this->assertEquals(50.0, (float) $item->current_stock);
        $this->assertEquals(700.00, (float) $item->cost_price);

        // Second purchase: 50 kg @ ৳800 / kg
        // Expected weighted average: (50*700 + 50*800) / 100 = 750.00
        $this->movementService->recordMovement(
            $branch->id,
            $location->id,
            $item->id,
            'goods_received',
            50.0,
            $kg->id,
            800.00,
            'test',
            null,
            null,
            'Second batch'
        );

        $item->refresh();
        $this->assertEquals(100.0, (float) $item->current_stock);
        $this->assertEquals(750.00, (float) $item->cost_price);

        // Third: Deduct 20 kg for kitchen issue
        $this->movementService->recordMovement(
            $branch->id,
            $location->id,
            $item->id,
            'direct_issue',
            20.0,
            $kg->id,
            null,
            'test',
            null,
            null,
            'Kitchen issue'
        );

        $item->refresh();
        $this->assertEquals(80.0, (float) $item->current_stock);
        $this->assertEquals(750.00, (float) $item->cost_price); // cost remains 750
    }

    public function test_recipe_costing_with_waste_allowance(): void
    {
        $dish = MenuItem::where('code', 'DISH-CHICKEN-BIRYANI')->firstOrFail();
        $recipe = $dish->recipe;
        $this->assertNotNull($recipe);

        $version = $recipe->activeVersion;
        $this->assertNotNull($version);

        // Verify calculated cost and food cost %
        $this->assertGreaterThan(100.0, (float) $version->calculated_cost_price);
        $this->assertLessThan(150.0, (float) $version->calculated_cost_price);
        $this->assertGreaterThan(30.0, (float) $version->food_cost_percentage);
        $this->assertLessThan(45.0, (float) $version->food_cost_percentage);

        // Suggested price test
        $suggested = $this->costingService->getSuggestedPrice((float) $version->calculated_cost_price, 32.0, 15.0);
        $this->assertArrayHasKey('net_suggested_price', $suggested);
        $this->assertArrayHasKey('gross_suggested_price_vat_inclusive', $suggested);
        $this->assertEquals(68.0, $suggested['gross_margin_percent']);
    }

    public function test_recipe_circular_dependency_is_caught(): void
    {
        $branch = Branch::where('code', 'MAIN-01')->firstOrFail();
        $portion = Unit::where('symbol', 'portion')->firstOrFail();

        // Recipe A
        $recipeA = Recipe::create([
            'branch_id' => $branch->id,
            'name' => 'Circular Recipe A',
            'recipe_type' => 'sub_recipe',
            'batch_yield_quantity' => 1.0,
            'yield_unit_id' => $portion->id,
        ]);
        $versionA = RecipeVersion::create([
            'recipe_id' => $recipeA->id,
            'version_no' => 1,
            'is_active' => true,
            'effective_date' => now()->toDateString(),
        ]);

        // Recipe B
        $recipeB = Recipe::create([
            'branch_id' => $branch->id,
            'name' => 'Circular Recipe B',
            'recipe_type' => 'sub_recipe',
            'batch_yield_quantity' => 1.0,
            'yield_unit_id' => $portion->id,
        ]);
        $versionB = RecipeVersion::create([
            'recipe_id' => $recipeB->id,
            'version_no' => 1,
            'is_active' => true,
            'effective_date' => now()->toDateString(),
        ]);

        // A depends on B
        RecipeLine::create([
            'recipe_version_id' => $versionA->id,
            'sub_recipe_id' => $recipeB->id,
            'quantity' => 1.0,
            'unit_id' => $portion->id,
        ]);

        // B depends on A (Circular!)
        RecipeLine::create([
            'recipe_version_id' => $versionB->id,
            'sub_recipe_id' => $recipeA->id,
            'quantity' => 1.0,
            'unit_id' => $portion->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->costingService->calculateVersionCost($versionA);
    }
}
