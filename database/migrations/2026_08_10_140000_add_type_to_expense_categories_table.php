<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategoriyalar endi kassaning ikkala tomoniga xizmat qiladi.
 *
 * Mavjud qatorlar xarajat kategoriyasi bo'lgani uchun standart qiymat
 * `expense` — eski ma'lumot o'z joyida qoladi.
 *
 * Noyoblik sharti ham `type` bilan kengaytiriladi: "Reklama" nomi kirimda
 * ham, chiqimda ham bo'lishi mumkin — bular boshqa-boshqa ro'yxatlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('type', 16)->default('expense')->after('user_id');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'user_id', 'name']);
            $table->unique(['shop_id', 'user_id', 'type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'user_id', 'type', 'name']);
            $table->unique(['shop_id', 'user_id', 'name']);
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
