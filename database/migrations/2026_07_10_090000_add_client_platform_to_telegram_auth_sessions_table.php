<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `client_platform` sessiyani boshlagan klientni bildiradi:
     *  - mobile : mobil ilova (eski xatti-harakat, standart qiymat — mavjud
     *             mobile klient hech narsa yubormasa ham buzilmasligi uchun)
     *  - web    : web ilova — muvaffaqiyatli tugagach foydalanuvchi mobil
     *             deep-link o'rniga WEB_APP_URL'ga yo'naltiriladi.
     */
    public function up(): void
    {
        Schema::table('telegram_auth_sessions', function (Blueprint $table) {
            $table->string('client_platform', 16)->default('mobile')->after('type');
            $table->index('client_platform');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_auth_sessions', function (Blueprint $table) {
            $table->dropIndex(['client_platform']);
            $table->dropColumn('client_platform');
        });
    }
};
