<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Do'konning o'z partiya birliklari (masalan "Laganda", "Tandir").
     *
     * `shop_id` NULL — tizim birligi (hammaga ko'rinadi).
     * `shop_id` to'ldirilgan — faqat shu do'kon uchun yaratilgan maxsus birlik.
     * Do'kon o'chirilsa birliklari ham ketadi.
     */
    public function up(): void
    {
        Schema::table('measurement_units', function (Blueprint $table) {
            $table->foreignUuid('shop_id')
                ->nullable()
                ->after('id')
                ->constrained('shops')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('measurement_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });
    }
};
