<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $financialTables = [
            'purchases',
            'supplier_payments',
            'employee_advances',
            'salary_payments',
            'operating_expenses',
            'daily_sales',
        ];

        foreach ($financialTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'reversal_status')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('reversal_status', 30)->default('active')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        $financialTables = [
            'purchases',
            'supplier_payments',
            'employee_advances',
            'salary_payments',
            'operating_expenses',
            'daily_sales',
        ];

        foreach ($financialTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'reversal_status')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('reversal_status', 30)->default('active')->nullable(false)->change();
                });
            }
        }
    }
};

