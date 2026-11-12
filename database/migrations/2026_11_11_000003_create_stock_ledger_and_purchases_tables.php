<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('invoice_no', 50)->nullable();
            $table->date('purchase_date')->index();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('confirmed')->index();
            $table->boolean('is_purchase_and_consume')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('purchase_quantity', 12, 4);
            $table->string('purchase_unit', 30);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('base_quantity', 12, 4);
            $table->decimal('base_unit_cost', 12, 4);
            $table->timestamps();
        });

        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('movement_type', [
                'opening_stock',
                'purchase_receipt',
                'consumption',
                'wastage',
                'adjustment_in',
                'adjustment_out',
                'purchase_return',
            ])->index();
            $table->date('transaction_date')->index();
            $table->decimal('quantity_in', 12, 4)->default(0.0000);
            $table->decimal('quantity_out', 12, 4)->default(0.0000);
            $table->decimal('balance_after', 12, 4);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_value', 12, 2);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['product_id', 'transaction_date']);
        });

        Schema::create('consumptions', function (Blueprint $table) {
            $table->id();
            $table->date('consumption_date')->index();
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('consumption_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumption_id')->constrained('consumptions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity_used', 12, 4); // in base_unit
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('consumable_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('total_cost', 12, 2);
            $table->integer('duration_days');
            $table->decimal('daily_allocated_cost', 12, 2);
            $table->enum('status', ['active', 'completed', 'adjusted'])->default('active');
            $table->decimal('confirmed_actual_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('wastages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('wastage_date')->index();
            $table->decimal('quantity', 12, 4); // in base_unit
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 12, 2);
            $table->enum('reason', ['spoiled', 'expired', 'burnt', 'accidental_loss', 'other']);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->date('count_date')->index();
            $table->enum('status', ['draft', 'confirmed'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('system_quantity', 12, 4);
            $table->decimal('counted_quantity', 12, 4);
            $table->decimal('difference_quantity', 12, 4);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('difference_value', 12, 2);
            $table->string('adjustment_reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('wastages');
        Schema::dropIfExists('consumable_allocations');
        Schema::dropIfExists('consumption_items');
        Schema::dropIfExists('consumptions');
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
