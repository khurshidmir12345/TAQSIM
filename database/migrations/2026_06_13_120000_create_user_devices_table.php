<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ko'p qurilma (multi-device) sessiya boshqaruvi uchun qurilma metama'lumotlari.
     *
     * Har bir aktiv sessiya (personal_access_tokens) bitta qurilmaga bog'lanadi.
     * Token o'chsa (logout/revoke) — qurilma yozuvi ham avtomatik o'chadi (cascade).
     * "Oxirgi faollik" personal_access_tokens.last_used_at dan o'qiladi (qo'shimcha yozuvsiz).
     */
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // personal_access_tokens.id (bigint) — sessiya bilan 1:1.
            $table->unsignedBigInteger('token_id');
            $table->foreign('token_id')
                ->references('id')->on('personal_access_tokens')
                ->cascadeOnDelete();

            $table->string('device_name')->nullable();   // "iPhone 14 / iOS 17"
            $table->string('platform', 32)->nullable();  // ios | android | web
            $table->string('app_version', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_active_at')->nullable();

            $table->timestamps();

            $table->unique('token_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
