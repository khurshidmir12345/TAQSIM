<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retseptsiz chiqim.
 *
 * - `shops.production_without_recipe`: do'kon sozlamasi — yoqilsa «Mahsulot
 *   chiqdi» sahifasida retsepti yo'q mahsulotlar ham chiqadi.
 * - `productions.recipe_id` NULL bo'lishi mumkin: retseptsiz chiqimda xom ashyo
 *   xarajati va un sarfi nol, faqat mahsulot soni yoziladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('production_without_recipe')->default(false);
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->uuid('recipe_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('production_without_recipe');
        });
        // recipe_id NOT NULL ga qaytarilmaydi — retseptsiz qatorlar bo'lishi mumkin.
    }
};
