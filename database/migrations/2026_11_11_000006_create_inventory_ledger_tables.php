<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100); // Main Store Room, Kitchen Station, Bar / Beverage, Cold Room
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code', 50)->index(); // Item SKU / Code
            $table->string('name', 150);
            $table->string('category', 50)->index(); // Meat & Poultry, Dairy, Vegetables, Dry Goods, Spices, Beverages, Packaging
            $table->foreignId('purchase_unit_id')->constrained('units');
            $table->foreignId('stock_unit_id')->constrained('units');
            $table->foreignId('recipe_unit_id')->constrained('units');
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->decimal('cost_price', 12, 4)->default(0.0000); // Moving weighted-average cost in stock_unit
            $table->decimal('current_stock', 12, 4)->default(0.0000);
            $table->decimal('min_reorder_level', 12, 4)->default(0.0000);
            $table->boolean('is_perishable')->default(false);
            $table->boolean('track_batches')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('stock_location_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->default(0.0000);
            $table->timestamps();

            $table->unique(['stock_item_id', 'stock_location_id']);
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->string('batch_no', 50)->index();
            $table->decimal('purchase_price', 12, 4);
            $table->decimal('initial_quantity', 12, 4);
            $table->decimal('remaining_quantity', 12, 4);
            $table->date('received_date');
            $table->date('expiry_date')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->enum('movement_type', [
                'goods_received',
                'recipe_consumption',
                'direct_issue',
                'wastage',
                'staff_meal',
                'transfer_out',
                'transfer_in',
                'physical_adjustment',
            ])->index();
            $table->decimal('quantity', 12, 4); // Signed (+ on received/transfer_in, - on consumption/waste)
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 12, 2);
            $table->string('reference_module', 50)->nullable(); // pos, procurement, inventory, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason_code', 100)->nullable(); // expired, spillage, burnt, count_variance
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['stock_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('stock_location_quantities');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('stock_locations');
    }
};
