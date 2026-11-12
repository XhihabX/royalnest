<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('role', 100);
            $table->date('joining_date');
            $table->enum('salary_type', ['monthly', 'daily'])->default('monthly');
            $table->decimal('agreed_salary', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date')->index();
            $table->enum('status', ['present', 'absent', 'half_day', 'leave'])->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('payment_date')->index();
            $table->enum('payment_type', ['salary_payment', 'salary_advance'])->default('salary_payment')->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('month_year', 20)->nullable()->index(); // e.g. "2026-11"
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('employees');
    }
};
