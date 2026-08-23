<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `days_before` manfiy ham bo'lishi mumkin.
 *
 * `-1` — muddat tugagandan keyingi kun. Aynan o'shanda foydalanuvchi
 * bo'limlar yopilganini sezadi, shuning uchun eng foydali xabar o'sha kuni
 * yuboriladi. Ustun `unsigned` bo'lgani uchun bunday qiymat sig'masdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_notices', function (Blueprint $table): void {
            $table->smallInteger('days_before')->change();
        });
    }

    public function down(): void
    {
        Schema::table('access_notices', function (Blueprint $table): void {
            $table->unsignedSmallInteger('days_before')->change();
        });
    }
};
