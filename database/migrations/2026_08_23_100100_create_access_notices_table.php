<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram orqali yuborilgan ogohlantirishlar jurnali.
 *
 * Job har kuni ishlaydi, shuning uchun "7 kun qoldi" xabari bir necha marta
 * yuborilib ketmasligi kerak. Har bir (foydalanuvchi, muddat, nuqta)
 * uchligi bir marta yoziladi va takror yuborilmaydi.
 *
 * `access_until` ham kalitga kiradi: admin muddatni uzaytirsa, yangi
 * muddat uchun ogohlantirishlar boshidan yuboriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_notices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('access_until');
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'access_until', 'days_before'], 'access_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_notices');
    }
};
