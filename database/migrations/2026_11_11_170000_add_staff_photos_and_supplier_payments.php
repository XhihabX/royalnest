<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nid_number', 50)->nullable()->after('phone');
            $table->string('photo_path', 255)->nullable()->after('nid_number');
            $table->string('nid_photo_path', 255)->nullable()->after('photo_path');
            $table->string('emergency_contact', 50)->nullable()->after('nid_photo_path');
            $table->text('address')->nullable()->after('emergency_contact');
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->string('voucher_no', 50)->nullable()->index();
            $table->string('payment_method', 50)->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        if (!Schema::hasColumn('purchases', 'payment_account_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('payment_account_id')->nullable()->after('paid_amount')->constrained('financial_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'payment_account_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropForeign(['payment_account_id']);
                $table->dropColumn('payment_account_id');
            });
        }

        Schema::dropIfExists('supplier_payments');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['nid_number', 'photo_path', 'nid_photo_path', 'emergency_contact', 'address']);
        });
    }
};

