<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Parol o'rnatilishi kerak" holati.
 *
 * SMS kodi bilan kirgan (parolni unutgan) foydalanuvchi tizimga kiradi, lekin
 * parolni hali qo'ymagan bo'ladi. Bu belgi turgan ekan:
 *  - ilova har kirganda parol o'rnatish ekranini ko'rsatadi;
 *  - eski parol so'ralmaydi — u ta'rifiga ko'ra uni bilmaydi.
 *
 * Keshda emas, ustunda saqlanadi: foydalanuvchi jarayonni tashlab ketsa,
 * bir necha kundan keyin qaytganda ham davom ettira olishi kerak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('must_set_password_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_set_password_at');
        });
    }
};
