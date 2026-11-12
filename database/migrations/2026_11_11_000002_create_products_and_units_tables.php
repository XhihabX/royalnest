<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->enum('type', ['raw_material', 'consumable', 'other_stock'])->default('raw_material');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->decimal('current_balance', 12, 2)->default(0.00); // Supplier payable balance
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->index();
            $table->enum('product_type', ['raw_material', 'consumable', 'other_stock'])->default('raw_material');
            $table->string('base_unit', 20); // g, kg, ml, L, pcs
            $table->string('purchase_unit', 30); // kg, sack, carton, tin, box, pcs
            $table->decimal('current_stock', 12, 4)->default(0.0000); // Always in base_unit
            $table->decimal('cost_price', 12, 4)->default(0.0000); // Moving weighted-average cost per base_unit
            $table->decimal('min_reorder_level', 12, 4)->default(0.0000);
            $table->boolean('is_consumable')->default(false);
            $table->integer('default_allocation_days')->nullable(); // For multi-day consumables (e.g. 4 days)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('purchase_unit', 30);
            $table->decimal('multiplier_to_base', 16, 6); // e.g. 1 sack = 25 kg, 1 kg = 1000 g
            $table->timestamps();

            $table->unique(['product_id', 'purchase_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unit_conversions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
    }
};
