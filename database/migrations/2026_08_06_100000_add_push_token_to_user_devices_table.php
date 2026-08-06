<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCM qurilma tokeni — push aynan shu token orqali yuboriladi.
 * Har bir qurilma (sessiya) uchun alohida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->string('push_token', 512)->nullable()->after('platform');
            $table->timestamp('push_token_updated_at')->nullable()->after('push_token');

            $table->index('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->dropIndex(['push_token']);
            $table->dropColumn(['push_token', 'push_token_updated_at']);
        });
    }
};
