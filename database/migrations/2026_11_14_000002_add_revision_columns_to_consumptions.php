<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumptions', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->index()->after('total_cost');
            $table->foreignId('revised_by_id')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('revised_at')->nullable()->after('revised_by_id');
            $table->string('payload_hash', 32)->nullable()->index()->after('revised_at');
        });
    }

    public function down(): void
    {
        Schema::table('consumptions', function (Blueprint $table) {
            $table->dropForeign(['revised_by_id']);
            $table->dropColumn([
                'status',
                'revised_by_id',
                'revised_at',
                'payload_hash',
            ]);
        });
    }
};
