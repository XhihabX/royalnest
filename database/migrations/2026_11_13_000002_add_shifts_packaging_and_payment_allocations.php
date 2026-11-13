<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Employee shift assignment
        Schema::table('employees', function (Blueprint $table) {
            $table->string('assigned_shift', 20)->default('both')->after('role');
        });

        // 2. Attendance shift support with safe historical retention (shift = null)
        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->string('shift', 20)->nullable()->default(null)->after('attendance_date');
            $table->index('employee_id');
        });

        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'attendance_date']);
            $table->unique(['employee_id', 'attendance_date', 'shift'], 'emp_att_emp_date_shift_unique');
        });

        // 3. Category & Product packaging type support
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type', 50)->default('raw_material')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 50)->default('raw_material')->change();
        });

        $existingPkg = DB::table('categories')->where('slug', 'packaging-materials')->first();
        if (!$existingPkg) {
            DB::table('categories')->insert([
                'slug' => 'packaging-materials',
                'name' => 'Packaging Materials',
                'type' => 'packaging',
                'description' => 'Polythene bags, food packaging, takeaway boxes, and foil',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Multi-bill and partial-bill supplier payment allocations
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'purchase_id'], 'sup_pay_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');

        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->dropUnique('emp_att_emp_date_shift_unique');
            $table->unique(['employee_id', 'attendance_date']);
            $table->dropColumn('shift');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('assigned_shift');
        });
    }
};
