<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push sozlamalari — qurilmada emas, serverda saqlanadi. Shunda foydalanuvchi
 * telefon almashtirsa yoki ikkinchi qurilmadan kirsa tanlovi saqlanib qoladi.
 *
 * `null` — hech narsa o'zgartirilmagan, ya'ni hammasi yoqiq (standart holat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_prefs')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_prefs');
        });
    }
};
