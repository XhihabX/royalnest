<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_cost_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('transaction_type', [
                'purchase',
                'consumption',
                'expense',
                'salary_payment',
                'salary_advance',
                'supplier_payment',
                'other',
            ]);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('expense_category', 50)->nullable();
            $table->string('default_unit', 20)->nullable();
            $table->decimal('last_unit_price', 12, 4)->default(0.0000);
            $table->foreignId('default_payment_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('daily_cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('reconciliation_date')->unique();
            $table->decimal('yesterday_closing_cash', 12, 2)->default(0.00);
            $table->decimal('opening_cash', 12, 2)->default(0.00);
            $table->decimal('opening_adjustment', 12, 2)->default(0.00);
            $table->decimal('today_cash_sales', 12, 2)->default(0.00);
            $table->decimal('other_cash_receipts', 12, 2)->default(0.00);
            $table->decimal('today_cash_payments', 12, 2)->default(0.00);
            $table->decimal('counted_closing_cash', 12, 2)->nullable();
            $table->decimal('estimated_cash_sales', 12, 2)->default(0.00);
            $table->decimal('recorded_non_cash_sales', 12, 2)->default(0.00);
            $table->decimal('estimated_total_sales', 12, 2)->default(0.00);
            $table->decimal('reconciliation_difference', 12, 2)->default(0.00);
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_cash_reconciliations');
        Schema::dropIfExists('saved_cost_templates');
    }
};
