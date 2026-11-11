<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->enum('type', [
                'asset',
                'liability',
                'equity',
                'revenue',
                'contra_revenue',
                'cogs',
                'expense',
                'other_income_expense',
            ])->index();
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_control')->default(false); // e.g. Accounts Receivable, Accounts Payable
            $table->boolean('is_system')->default(false);  // protected core accounts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // e.g. "November 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['start_date', 'end_date']);
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('chart_account_id')->constrained('chart_of_accounts');
            $table->string('name', 100); // e.g. Cash Drawer 1, bKash Merchant, Bank Account
            $table->enum('type', [
                'cash_drawer',
                'petty_cash',
                'bank',
                'bkash',
                'nagad',
                'card_clearing',
            ])->index();
            $table->string('account_number', 50)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->decimal('current_balance', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no', 30)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->date('business_date')->index();
            $table->date('transaction_date');
            $table->timestamp('posting_date')->useCurrent();
            $table->enum('source_module', [
                'pos',
                'procurement',
                'inventory',
                'expense',
                'payroll',
                'manual',
            ])->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 12, 2)->default(0.00);
            $table->decimal('total_credit', 12, 2)->default(0.00);
            $table->boolean('is_posted')->default(true);
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_by_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['source_module', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->foreignId('chart_account_id')->constrained('chart_of_accounts');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('debit', 12, 2)->default(0.00);
            $table->decimal('credit', 12, 2)->default(0.00);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['chart_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('chart_of_accounts');
    }
};

