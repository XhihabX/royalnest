<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('company_name', 150)->nullable()->after('name');
            $table->string('alternative_phone', 50)->nullable()->after('phone');
            $table->string('business_location', 255)->nullable()->after('address');
            $table->string('category', 100)->nullable()->after('business_location');
            $table->enum('status', ['potential', 'active', 'inactive', 'archived'])->default('potential')->index()->after('category');
            $table->string('preferred_payment_terms', 100)->nullable()->after('status');
            $table->text('notes')->nullable()->after('preferred_payment_terms');
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name', 150);
            $table->string('product_category', 100)->nullable();
            $table->string('purchase_unit', 50);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('min_order_quantity', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('advance_date')->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('payment_method', 50)->default('cash');
            $table->string('voucher_no', 50)->index();
            $table->string('reason', 255)->nullable();
            $table->decimal('remaining_balance', 12, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_id', 50)->nullable()->unique()->after('id');
            $table->string('alternative_phone', 50)->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive', 'on_leave', 'archived'])->default('active')->index()->after('is_active');
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('agreed_salary');
            $table->text('notes')->nullable()->after('hourly_rate');
        });

        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('advance_date')->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('payment_method', 50)->default('cash');
            $table->string('voucher_no', 50)->index();
            $table->string('reason', 255)->nullable();
            $table->decimal('remaining_balance', 12, 2);
            $table->enum('status', ['pending', 'partially_deducted', 'settled'])->default('pending')->index();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_payment_id')->nullable()->constrained('salary_payments')->cascadeOnDelete();
            $table->foreignId('employee_advance_id')->nullable()->constrained('employee_advances')->nullOnDelete();
            $table->date('deduction_date')->index();
            $table->decimal('amount', 12, 2);
            $table->enum('deduction_type', ['advance_recovery', 'penalty', 'unpaid_leave', 'other'])->default('advance_recovery');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_deductions');
        Schema::dropIfExists('employee_advances');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'alternative_phone', 'status', 'hourly_rate', 'notes']);
        });

        Schema::dropIfExists('supplier_advances');
        Schema::dropIfExists('supplier_products');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'alternative_phone', 'business_location', 'category', 'status', 'preferred_payment_terms', 'notes']);
        });
    }
};
