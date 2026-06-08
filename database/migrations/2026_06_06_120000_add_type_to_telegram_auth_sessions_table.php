<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `type` ustuni sessiya maqsadini ajratadi:
     *  - login   : Telegram orqali kirish (yangi/qaytuvchi foydalanuvchi)
     *  - connect : mavjud (auth qilingan) foydalanuvchiga Telegramni bog'lash
     */
    public function up(): void
    {
        Schema::table('telegram_auth_sessions', function (Blueprint $table) {
            $table->string('type', 16)->default('login')->after('session_token');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_auth_sessions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
