<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foydalanuvchining to'liq kirish muddati.
 *
 * `access_until` — shu sanagacha barcha bo'limlar ochiq. O'tib ketgach
 * `config/access.php` dagi `paid_features` yopiladi (statistika,
 * buyurtmalar, xodimlar, ikkinchi biznes). Qolgani doim bepul.
 *
 * `access_source` — muddat qayerdan kelgan: 'trial' (ro'yxatdan o'tganda
 * avtomat) yoki 'paid' (admin qo'lda uzaytirgan). Faqat hisobot uchun —
 * ruxsat qarori faqat sanaga qarab chiqariladi.
 *
 * Hisob egaga bog'lanadi: xodim (seller) egasining muddati ichida ishlaydi.
 * `Shop::owner()` izohiga qarang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('access_until')->nullable()->after('blocked_at')->index();
            $table->string('access_source', 16)->default('trial')->after('access_until');
        });

        // Mavjud foydalanuvchilar bugungi kundan sinov muddatini oladi —
        // deploy kuni hech kimda hech narsa yopilib qolmasin.
        DB::table('users')
            ->whereNull('access_until')
            ->update([
                'access_until' => now()->addDays((int) config('access.trial_days', 30)),
                'access_source' => 'trial',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['access_until', 'access_source']);
        });
    }
};
