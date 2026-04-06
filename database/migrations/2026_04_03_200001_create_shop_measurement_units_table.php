<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_measurement_units', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('measurement_unit_id')->constrained('measurement_units')->cascadeOnDelete();
            $table->unique(['shop_id', 'measurement_unit_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_measurement_units');
    }
};
