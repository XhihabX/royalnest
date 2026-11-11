<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Kilogram, Gram, Liter, Milliliter, Piece, Portion, Box, Carton
            $table->string('symbol', 20)->unique(); // kg, g, L, ml, pcs, portion, box, ctn
            $table->enum('type', ['weight', 'volume', 'count', 'custom'])->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('multiplier', 16, 6); // e.g. from kg to g = 1000.000000
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['from_unit_id', 'to_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units');
    }
};
