<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Employee;
use App\Models\FinancialAccount;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoyalNestV1Seeder extends Seeder
{
    public function run(): void
    {
        $stockService = app(StockLedgerService::class);

        // 1. Branch
        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN-01'],
            [
                'name' => 'Royal Nest Café & Restaurant',
                'phone' => '+8801700000000',
                'email' => 'info@royalnest.com',
                'address' => 'House 12, Road 5, Dhanmondi, Dhaka',
                'currency' => 'BDT',
                'currency_symbol' => '৳',
                'timezone' => 'Asia/Dhaka',
                'is_active' => true,
            ]
        );

        // 2. Roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner'], ['display_name' => 'Owner / Director', 'is_system' => true]);
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'General Manager', 'is_system' => true]);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier'], ['display_name' => 'Cashier', 'is_system' => true]);
        $staffRole = Role::firstOrCreate(['name' => 'staff'], ['display_name' => 'Staff', 'is_system' => true]);

        // 3. User
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@royalnest.com'],
            [
                'branch_id' => $branch->id,
                'name' => 'Royal Nest Admin',
                'phone' => '01711112222',
                'primary_role' => 'owner',
                'password' => Hash::make('password'),
                'security_pin' => Hash::make('1234'),
                'is_active' => true,
            ]
        );
        $adminUser->roles()->syncWithoutDetaching([$ownerRole->id]);

        // 4. Financial Accounts
        $cashDrawer = FinancialAccount::firstOrCreate(
            ['name' => 'Cash Drawer'],
            [
                'type' => 'cash',
                'opening_balance' => 5000.00,
                'current_balance' => 5000.00,
                'is_active' => true,
            ]
        );

        $bkash = FinancialAccount::firstOrCreate(
            ['name' => 'bKash Merchant'],
            [
                'type' => 'bkash',
                'account_number' => '01700000001',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active' => true,
            ]
        );

        $nagad = FinancialAccount::firstOrCreate(
            ['name' => 'Nagad Merchant'],
            [
                'type' => 'nagad',
                'account_number' => '01800000001',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active' => true,
            ]
        );

        $bank = FinancialAccount::firstOrCreate(
            ['name' => 'BRAC Bank Current'],
            [
                'type' => 'bank',
                'account_number' => '1501203948501',
                'opening_balance' => 50000.00,
                'current_balance' => 50000.00,
                'is_active' => true,
            ]
        );

        // 5. Categories
        $catRaw = Category::firstOrCreate(['slug' => 'raw-materials'], [
            'name' => 'Raw Materials',
            'type' => 'raw_material',
            'description' => 'Meat, spices, rice, cooking oil and ingredients',
        ]);

        $catConsumables = Category::firstOrCreate(['slug' => 'consumables'], [
            'name' => 'Consumables',
            'type' => 'consumable',
            'description' => 'Packaging, tissue paper, cleaning items',
        ]);

        $catOther = Category::firstOrCreate(['slug' => 'other-stock'], [
            'name' => 'Other Stock Items',
            'type' => 'other_stock',
            'description' => 'Miscellaneous restaurant supplies',
        ]);

        // 6. Suppliers
        $supMeat = Supplier::firstOrCreate(['name' => 'Bengal Meat Supplies'], [
            'contact_person' => 'Md. Faruk',
            'phone' => '01712345678',
            'address' => 'Hazaribagh, Dhaka',
        ]);

        $supSpice = Supplier::firstOrCreate(['name' => 'Al-Madina Spice Mart'], [
            'contact_person' => 'Haji Kashem',
            'phone' => '01812345678',
            'address' => 'Moulvibazar, Dhaka',
        ]);

        $supMetro = Supplier::firstOrCreate(['name' => 'Metro Wholesale Store'], [
            'contact_person' => 'Tanvir Ahmed',
            'phone' => '01912345678',
            'address' => 'Kawran Bazar, Dhaka',
        ]);

        // 7. Products and conversions
        $productsConfig = [
            [
                'name' => 'Cardamom (Green Elaichi)',
                'category_id' => $catRaw->id,
                'product_type' => 'raw_material',
                'base_unit' => 'g',
                'purchase_unit' => 'kg',
                'conversion_multiplier' => 1000.0,
                'cost_price' => 2.50, // ৳2.50/g (৳2500/kg)
                'initial_stock' => 100.0, // 100g
                'min_reorder_level' => 50.0,
                'is_consumable' => false,
                'supplier_id' => $supSpice->id,
            ],
            [
                'name' => 'Basmati Rice (Classic)',
                'category_id' => $catRaw->id,
                'product_type' => 'raw_material',
                'base_unit' => 'kg',
                'purchase_unit' => 'sack',
                'conversion_multiplier' => 25.0, // 1 sack = 25 kg
                'cost_price' => 110.00,
                'initial_stock' => 50.0, // 50kg (2 sacks)
                'min_reorder_level' => 15.0,
                'is_consumable' => false,
                'supplier_id' => $supMetro->id,
            ],
            [
                'name' => 'Mustard Oil (Pure)',
                'category_id' => $catRaw->id,
                'product_type' => 'raw_material',
                'base_unit' => 'ml',
                'purchase_unit' => 'L',
                'conversion_multiplier' => 1000.0,
                'cost_price' => 0.22, // ৳220/L
                'initial_stock' => 5000.0, // 5,000ml (5L)
                'min_reorder_level' => 1000.0,
                'is_consumable' => false,
                'supplier_id' => $supMetro->id,
            ],
            [
                'name' => 'Broiler Chicken (Dressed)',
                'category_id' => $catRaw->id,
                'product_type' => 'raw_material',
                'base_unit' => 'kg',
                'purchase_unit' => 'kg',
                'conversion_multiplier' => 1.0,
                'cost_price' => 180.00,
                'initial_stock' => 20.0,
                'min_reorder_level' => 10.0,
                'is_consumable' => false,
                'supplier_id' => $supMeat->id,
            ],
            [
                'name' => 'Tissue Box (Tabletop 200 pulls)',
                'category_id' => $catConsumables->id,
                'product_type' => 'consumable',
                'base_unit' => 'pcs',
                'purchase_unit' => 'box',
                'conversion_multiplier' => 1.0,
                'cost_price' => 120.00,
                'initial_stock' => 10.0,
                'min_reorder_level' => 5.0,
                'is_consumable' => true,
                'default_allocation_days' => 4,
                'supplier_id' => $supMetro->id,
            ],
        ];

        foreach ($productsConfig as $pData) {
            $product = Product::firstOrCreate(
                ['name' => $pData['name']],
                [
                    'category_id' => $pData['category_id'],
                    'product_type' => $pData['product_type'],
                    'base_unit' => $pData['base_unit'],
                    'purchase_unit' => $pData['purchase_unit'],
                    'cost_price' => $pData['cost_price'],
                    'min_reorder_level' => $pData['min_reorder_level'],
                    'is_consumable' => $pData['is_consumable'],
                    'default_allocation_days' => $pData['default_allocation_days'] ?? null,
                    'supplier_id' => $pData['supplier_id'],
                    'current_stock' => 0.0,
                    'is_active' => true,
                ]
            );

            // Conversion
            ProductUnitConversion::firstOrCreate(
                ['product_id' => $product->id, 'purchase_unit' => $pData['purchase_unit']],
                ['multiplier_to_base' => $pData['conversion_multiplier']]
            );

            // Record initial opening stock if ledger is empty for this product
            if ($product->stockLedgers()->count() === 0 && $pData['initial_stock'] > 0) {
                $stockService->recordMovement(
                    product: $product,
                    movementType: 'opening_stock',
                    quantityIn: $pData['initial_stock'],
                    quantityOut: 0,
                    unitCost: $pData['cost_price'],
                    transactionDate: now()->toDateString(),
                    userId: $adminUser->id,
                    notes: 'Initial opening stock setup'
                );
            }
        }

        // 8. Employees
        Employee::firstOrCreate(['name' => 'Rahim Uddin'], [
            'phone' => '01711223344',
            'role' => 'Head Chef',
            'joining_date' => '2026-01-01',
            'salary_type' => 'monthly',
            'agreed_salary' => 25000.00,
            'is_active' => true,
        ]);

        Employee::firstOrCreate(['name' => 'Karim Ali'], [
            'phone' => '01811223344',
            'role' => 'Senior Waiter',
            'joining_date' => '2026-02-01',
            'salary_type' => 'monthly',
            'agreed_salary' => 15000.00,
            'is_active' => true,
        ]);

        Employee::firstOrCreate(['name' => 'Sumon Das'], [
            'phone' => '01911223344',
            'role' => 'Kitchen Assistant',
            'joining_date' => '2026-03-01',
            'salary_type' => 'daily',
            'agreed_salary' => 600.00,
            'is_active' => true,
        ]);
    }
}

