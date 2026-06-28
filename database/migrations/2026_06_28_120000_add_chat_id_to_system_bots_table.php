<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_bots', function (Blueprint $table) {
            // 'notify' turidagi botlar uchun xabar yuboriladigan guruh/kanal ID.
            $table->string('chat_id')->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('system_bots', function (Blueprint $table) {
            $table->dropColumn('chat_id');
        });
    }
};
