<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique();        // 'bakery', 'meat_grill', ...
            $table->string('icon', 10);                 // emoji: 🍞 🥩 🎂 🍔 🥤 🏭
            $table->string('color', 20);                // hex: #FF6B35
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Localized names
            $table->string('name_uz');
            $table->string('name_uz_cyrl')->nullable();
            $table->string('name_ru');
            $table->string('name_kk')->nullable();
            $table->string('name_ky')->nullable();
            $table->string('name_tr')->nullable();

            // Terminology per locale (JSON)
            // Keys: rawMaterial, rawMaterialUnit, batchLabel, batchUnit,
            //        productLabel, productUnit, productionVerb,
            //        recipeLabel, categoryLabel
            $table->json('terminology');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_types');
    }
};
