<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['ingredient', 'batch'])->index();
            $table->string('code', 20)->unique();
            $table->string('name_uz', 50);
            $table->string('name_uz_cyrl', 50);
            $table->string('name_ru', 50);
            $table->string('name_kk', 50);
            $table->string('name_ky', 50);
            $table->string('name_tr', 50);
            $table->text('example_uz')->nullable();
            $table->text('example_ru')->nullable();
            $table->string('icon', 10)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
    }
};
