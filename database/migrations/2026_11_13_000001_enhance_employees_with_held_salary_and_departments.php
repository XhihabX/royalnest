<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('department', 50)->default('kitchen')->after('role')->index();
            $table->string('tags', 255)->nullable()->after('department');
            $table->unsignedSmallInteger('held_salary_days')->default(0)->after('agreed_salary');
            $table->decimal('held_salary_amount', 12, 2)->default(0.00)->after('held_salary_days');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['department', 'tags', 'held_salary_days', 'held_salary_amount']);
        });
    }
};

