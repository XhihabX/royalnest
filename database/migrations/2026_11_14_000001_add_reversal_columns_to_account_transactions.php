<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->string('reversal_status', 30)->default('active')->index()->after('type');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index()->after('reversal_status');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete()->after('reversed_at');
            $table->text('reversal_reason')->nullable()->after('reversed_by');
            $table->unsignedBigInteger('compensating_transaction_id')->nullable()->index()->after('reversal_reason');

            $table->foreign('reversal_of_id')->references('id')->on('account_transactions')->nullOnDelete();
            $table->foreign('compensating_transaction_id')->references('id')->on('account_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_id']);
            $table->dropForeign(['compensating_transaction_id']);
            $table->dropForeign(['reversed_by']);
            $table->dropColumn([
                'reversal_status',
                'reversal_of_id',
                'reversed_at',
                'reversed_by',
                'reversal_reason',
                'compensating_transaction_id',
            ]);
        });
    }
};
