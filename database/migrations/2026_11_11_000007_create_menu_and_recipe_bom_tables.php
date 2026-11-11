<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100); // Starters, Main Courses, Biryani & Rice, Grills & Kebabs, Beverages, Desserts
            $table->string('slug', 100);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'slug']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->index(); // Menu SKU
            $table->text('description')->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(15.00);
            $table->enum('vat_mode', ['inclusive', 'exclusive'])->default('inclusive');
            $table->enum('kitchen_station', ['kitchen', 'grill', 'bar', 'dessert', 'beverage'])->default('kitchen');
            $table->boolean('is_available')->default(true);
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name', 50); // Regular, Large, Half, Full
            $table->decimal('price_modifier', 12, 2)->default(0.00);
            $table->decimal('selling_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100); // Extra Cheese, Spicy, Less Sugar, Extra Gravy
            $table->decimal('price', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('menu_item_variants')->nullOnDelete();
            $table->string('name', 150); // Chicken Biryani Recipe, or "Special Biryani Spice Gravy (Sub-Recipe)"
            $table->enum('recipe_type', ['menu_item', 'sub_recipe'])->default('menu_item');
            $table->decimal('batch_yield_quantity', 12, 4)->default(1.0000);
            $table->foreignId('yield_unit_id')->constrained('units');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->integer('version_no')->default(1);
            $table->boolean('is_active')->default(true);
            $table->date('effective_date');
            $table->decimal('calculated_cost_price', 12, 4)->default(0.0000); // Theoretical Cost
            $table->decimal('food_cost_percentage', 5, 2)->default(0.00); // (Cost / Net Price) * 100
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'version_no']);
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->nullable()->constrained('stock_items')->nullOnDelete();
            $table->foreignId('sub_recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->decimal('quantity', 12, 4); // In unit_id
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('waste_percent', 5, 2)->default(0.00); // e.g. 5% trim loss
            $table->decimal('yield_percent', 5, 2)->default(100.00); // e.g. 95% usable yield
            $table->decimal('calculated_cost', 12, 4)->default(0.0000);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('recipe_versions');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('menu_modifiers');
        Schema::dropIfExists('menu_item_variants');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
