<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['cash', 'bank', 'bkash', 'nagad', 'other'])->index();
            $table->string('account_number', 50)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->decimal('current_balance', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->enum('type', ['deposit', 'withdrawal', 'transfer_in', 'transfer_out'])->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('related_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('daily_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sales_date')->unique();
            $table->decimal('total_sales', 12, 2);
            $table->decimal('cash_sales', 12, 2)->default(0.00);
            $table->decimal('bkash_sales', 12, 2)->default(0.00);
            $table->decimal('nagad_sales', 12, 2)->default(0.00);
            $table->decimal('card_bank_sales', 12, 2)->default(0.00);
            $table->decimal('other_sales', 12, 2)->default(0.00);
            $table->decimal('discrepancy_amount', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->enum('category', [
                'electricity',
                'gas',
                'water',
                'rent',
                'transport',
                'repairs',
                'cleaning',
                'marketing',
                'other',
            ])->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('payee', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expenses');
        Schema::dropIfExists('daily_sales');
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('financial_accounts');
    }
};

