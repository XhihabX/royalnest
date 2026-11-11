<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\RecipeVersion;
use App\Models\StockItem;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\RecipeCostingService;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;

class RoyalNestMenuAndInventorySeeder extends Seeder
{
    public function run(
        RecipeCostingService $costingService,
        StockMovementService $movementService
    ): void {
        $branch = Branch::where('code', 'MAIN-01')->firstOrFail();
        $inventoryAssetCoa = ChartOfAccount::where('code', '1100')->first();

        // 1. Units of Measure
        $kg = Unit::firstOrCreate(['symbol' => 'kg'], ['name' => 'Kilogram', 'type' => 'weight', 'is_system' => true]);
        $g  = Unit::firstOrCreate(['symbol' => 'g'], ['name' => 'Gram', 'type' => 'weight', 'is_system' => true]);
        $l  = Unit::firstOrCreate(['symbol' => 'L'], ['name' => 'Liter', 'type' => 'volume', 'is_system' => true]);
        $ml = Unit::firstOrCreate(['symbol' => 'ml'], ['name' => 'Milliliter', 'type' => 'volume', 'is_system' => true]);
        $pcs = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'type' => 'count', 'is_system' => true]);
        $can = Unit::firstOrCreate(['symbol' => 'can'], ['name' => 'Can', 'type' => 'count', 'is_system' => true]);
        $portion = Unit::firstOrCreate(['symbol' => 'portion'], ['name' => 'Portion', 'type' => 'count', 'is_system' => true]);
        $ctn24 = Unit::firstOrCreate(['symbol' => 'ctn_24'], ['name' => 'Carton 24 pcs', 'type' => 'count', 'is_system' => true]);

        // 2. Unit Conversions
        UnitConversion::firstOrCreate(
            ['from_unit_id' => $kg->id, 'to_unit_id' => $g->id],
            ['multiplier' => 1000.000000, 'is_system' => true]
        );
        UnitConversion::firstOrCreate(
            ['from_unit_id' => $l->id, 'to_unit_id' => $ml->id],
            ['multiplier' => 1000.000000, 'is_system' => true]
        );
        UnitConversion::firstOrCreate(
            ['from_unit_id' => $ctn24->id, 'to_unit_id' => $pcs->id],
            ['multiplier' => 24.000000, 'is_system' => true]
        );
        UnitConversion::firstOrCreate(
            ['from_unit_id' => $ctn24->id, 'to_unit_id' => $can->id],
            ['multiplier' => 24.000000, 'is_system' => true]
        );

        // 3. Stock Locations
        $mainStore = StockLocation::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'LOC-MAIN'],
            ['name' => 'Main Dry & Cold Store', 'description' => 'Central stockroom for bulk ingredients']
        );
        $kitchen = StockLocation::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'LOC-KITCHEN'],
            ['name' => 'Kitchen Prep Station', 'description' => 'Frontline kitchen stock for daily service']
        );
        $bar = StockLocation::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'LOC-BAR'],
            ['name' => 'Coffee & Beverage Bar', 'description' => 'Espresso machine and beverage counter']
        );

        // 4. Stock Items
        $itemsData = [
            ['code' => 'RAW-CHICKEN', 'name' => 'Fresh Farm Chicken', 'category' => 'Meat & Poultry', 'cost' => 210.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-RICE', 'name' => 'Premium Kalijira Basmati Rice', 'category' => 'Dry Goods', 'cost' => 140.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-OIL', 'name' => 'Pure Mustard Oil', 'category' => 'Dry Goods', 'cost' => 220.0000, 'p_unit' => $l, 's_unit' => $l, 'r_unit' => $ml],
            ['code' => 'RAW-GHEE', 'name' => 'Pure Milk Ghee', 'category' => 'Dairy', 'cost' => 1200.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-SPICE', 'name' => 'Royal Garam Masala Blend', 'category' => 'Spices', 'cost' => 950.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-ONION', 'name' => 'Deshi Red Onions', 'category' => 'Vegetables', 'cost' => 85.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-GINGER-GARLIC', 'name' => 'Fresh Ginger & Garlic Paste', 'category' => 'Vegetables', 'cost' => 250.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-MILK', 'name' => 'Fresh Whole Milk', 'category' => 'Dairy', 'cost' => 90.0000, 'p_unit' => $l, 's_unit' => $l, 'r_unit' => $ml],
            ['code' => 'RAW-COFFEE', 'name' => 'Arabica Dark Roast Coffee Beans', 'category' => 'Beverages', 'cost' => 2400.0000, 'p_unit' => $kg, 's_unit' => $kg, 'r_unit' => $g],
            ['code' => 'RAW-COKE', 'name' => 'Coca-Cola 250ml Can', 'category' => 'Beverages', 'cost' => 38.0000, 'p_unit' => $ctn24, 's_unit' => $can, 'r_unit' => $can],
        ];

        $stockItems = [];
        foreach ($itemsData as $data) {
            $stockItems[$data['code']] = StockItem::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'purchase_unit_id' => $data['p_unit']->id,
                    'stock_unit_id' => $data['s_unit']->id,
                    'recipe_unit_id' => $data['r_unit']->id,
                    'chart_account_id' => $inventoryAssetCoa?->id,
                    'cost_price' => $data['cost'],
                    'current_stock' => 0.0000,
                    'min_reorder_level' => 10.0000,
                    'is_active' => true,
                ]
            );
        }

        // 5. Initial Goods Receipt via StockMovementService
        $movementService->recordMovement($branch->id, $mainStore->id, $stockItems['RAW-CHICKEN']->id, 'goods_received', 50.0, $kg->id, 210.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $mainStore->id, $stockItems['RAW-RICE']->id, 'goods_received', 100.0, $kg->id, 140.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $mainStore->id, $stockItems['RAW-GHEE']->id, 'goods_received', 20.0, $kg->id, 1200.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $mainStore->id, $stockItems['RAW-SPICE']->id, 'goods_received', 10.0, $kg->id, 950.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $kitchen->id, $stockItems['RAW-ONION']->id, 'goods_received', 40.0, $kg->id, 85.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $bar->id, $stockItems['RAW-COFFEE']->id, 'goods_received', 10.0, $kg->id, 2400.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $bar->id, $stockItems['RAW-MILK']->id, 'goods_received', 30.0, $l->id, 90.00, 'initial_stock', null, null, 'Opening stock load');
        $movementService->recordMovement($branch->id, $bar->id, $stockItems['RAW-COKE']->id, 'goods_received', 120.0, $can->id, 38.00, 'initial_stock', null, null, 'Opening stock load');

        // 6. Menu Categories
        $catBiryani = MenuCategory::firstOrCreate(['branch_id' => $branch->id, 'slug' => 'biryani-rice'], ['name' => 'Biryani & Rice Specialties', 'display_order' => 1]);
        $catGrill = MenuCategory::firstOrCreate(['branch_id' => $branch->id, 'slug' => 'grills-kebabs'], ['name' => 'Grills & Kebabs', 'display_order' => 2]);
        $catBar = MenuCategory::firstOrCreate(['branch_id' => $branch->id, 'slug' => 'coffee-beverages'], ['name' => 'Artisan Coffee & Drinks', 'display_order' => 3]);

        // 7. Menu Items
        $dishBiryani = MenuItem::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DISH-CHICKEN-BIRYANI'],
            [
                'menu_category_id' => $catBiryani->id,
                'name' => 'Royal Special Chicken Biryani',
                'description' => 'Fragrant Kalijira rice cooked in pure ghee with succulent farm chicken, caramelized onions and secret aromatic spices.',
                'selling_price' => 380.00,
                'vat_rate' => 15.00,
                'vat_mode' => 'inclusive',
                'kitchen_station' => 'kitchen',
                'is_available' => true,
            ]
        );

        $dishCappuccino = MenuItem::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'DISH-CAPPUCCINO'],
            [
                'menu_category_id' => $catBar->id,
                'name' => 'Royal Espresso Cappuccino',
                'description' => 'Rich double shot espresso pulled from Arabica beans, topped with creamy steamed fresh milk foam.',
                'selling_price' => 180.00,
                'vat_rate' => 15.00,
                'vat_mode' => 'inclusive',
                'kitchen_station' => 'bar',
                'is_available' => true,
            ]
        );

        // 8. Recipe 1: Royal Special Chicken Biryani (BOM)
        $biryaniRecipe = Recipe::firstOrCreate(
            ['branch_id' => $branch->id, 'menu_item_id' => $dishBiryani->id],
            [
                'name' => 'Royal Special Chicken Biryani Standard Recipe',
                'recipe_type' => 'menu_item',
                'batch_yield_quantity' => 1.0000,
                'yield_unit_id' => $portion->id,
                'notes' => 'Single standard portion yield',
            ]
        );

        $biryaniVersion = RecipeVersion::firstOrCreate(
            ['recipe_id' => $biryaniRecipe->id, 'version_no' => 1],
            [
                'is_active' => true,
                'effective_date' => now()->toDateString(),
                'notes' => 'Master baseline recipe v1',
            ]
        );

        // Biryani Ingredients (Chicken 250g, Rice 180g, Ghee 25g, Spices 5g, Onions 50g)
        RecipeLine::firstOrCreate(['recipe_version_id' => $biryaniVersion->id, 'stock_item_id' => $stockItems['RAW-CHICKEN']->id], [
            'quantity' => 250.0000,
            'unit_id' => $g->id,
            'waste_percent' => 5.00, // 5% trimming waste
            'yield_percent' => 95.00,
        ]);
        RecipeLine::firstOrCreate(['recipe_version_id' => $biryaniVersion->id, 'stock_item_id' => $stockItems['RAW-RICE']->id], [
            'quantity' => 180.0000,
            'unit_id' => $g->id,
            'waste_percent' => 0.00,
            'yield_percent' => 100.00,
        ]);
        RecipeLine::firstOrCreate(['recipe_version_id' => $biryaniVersion->id, 'stock_item_id' => $stockItems['RAW-GHEE']->id], [
            'quantity' => 25.0000,
            'unit_id' => $g->id,
            'waste_percent' => 0.00,
            'yield_percent' => 100.00,
        ]);
        RecipeLine::firstOrCreate(['recipe_version_id' => $biryaniVersion->id, 'stock_item_id' => $stockItems['RAW-SPICE']->id], [
            'quantity' => 5.0000,
            'unit_id' => $g->id,
            'waste_percent' => 0.00,
            'yield_percent' => 100.00,
        ]);
        RecipeLine::firstOrCreate(['recipe_version_id' => $biryaniVersion->id, 'stock_item_id' => $stockItems['RAW-ONION']->id], [
            'quantity' => 50.0000,
            'unit_id' => $g->id,
            'waste_percent' => 10.00, // 10% peeling waste
            'yield_percent' => 90.00,
        ]);

        // Calculate theoretical cost for Biryani
        $costingService->calculateVersionCost($biryaniVersion);

        // 9. Recipe 2: Royal Espresso Cappuccino
        $cappuccinoRecipe = Recipe::firstOrCreate(
            ['branch_id' => $branch->id, 'menu_item_id' => $dishCappuccino->id],
            [
                'name' => 'Royal Cappuccino Recipe',
                'recipe_type' => 'menu_item',
                'batch_yield_quantity' => 1.0000,
                'yield_unit_id' => $portion->id,
                'notes' => '1 cup 220ml beverage yield',
            ]
        );

        $cappuccinoVersion = RecipeVersion::firstOrCreate(
            ['recipe_id' => $cappuccinoRecipe->id, 'version_no' => 1],
            [
                'is_active' => true,
                'effective_date' => now()->toDateString(),
                'notes' => 'Barista espresso recipe',
            ]
        );

        RecipeLine::firstOrCreate(['recipe_version_id' => $cappuccinoVersion->id, 'stock_item_id' => $stockItems['RAW-COFFEE']->id], [
            'quantity' => 18.0000,
            'unit_id' => $g->id,
            'waste_percent' => 2.00, // grinder retention waste
            'yield_percent' => 98.00,
        ]);
        RecipeLine::firstOrCreate(['recipe_version_id' => $cappuccinoVersion->id, 'stock_item_id' => $stockItems['RAW-MILK']->id], [
            'quantity' => 180.0000,
            'unit_id' => $ml->id,
            'waste_percent' => 5.00, // steaming pitcher waste
            'yield_percent' => 95.00,
        ]);

        // Calculate theoretical cost for Cappuccino
        $costingService->calculateVersionCost($cappuccinoVersion);
    }
}
