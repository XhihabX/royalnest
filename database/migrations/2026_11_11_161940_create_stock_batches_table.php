<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->string('batch_no', 60)->index();
            $table->date('received_date')->index();
            $table->decimal('initial_quantity', 12, 4); // in base_unit
            $table->decimal('remaining_quantity', 12, 4); // in base_unit
            $table->decimal('unit_cost', 12, 4); // exact purchase cost per base_unit
            $table->boolean('is_exhausted')->default(false)->index();
            $table->timestamps();

            $table->index(['product_id', 'is_exhausted', 'received_date']);
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->foreignId('stock_batch_id')->nullable()->after('product_id')->constrained('stock_batches')->nullOnDelete();
        });

        Schema::table('consumption_items', function (Blueprint $table) {
            $table->foreignId('stock_batch_id')->nullable()->after('product_id')->constrained('stock_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consumption_items', function (Blueprint $table) {
            $table->dropForeign(['stock_batch_id']);
            $table->dropColumn('stock_batch_id');
        });

        Schema::table('stock_ledger', function (Blueprint $table) {
            $table->dropForeign(['stock_batch_id']);
            $table->dropColumn('stock_batch_id');
        });

        Schema::dropIfExists('stock_batches');
    }
};
