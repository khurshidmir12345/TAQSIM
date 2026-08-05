<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support guruhiga forward qilingan har bir xabar uchun bog'lanish yozuvi.
 *
 * Admin guruhda o'sha xabarga reply qilganda, `group_message_id` bo'yicha
 * foydalanuvchi topiladi va javob unga yuboriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('system_bot_id')->constrained('system_bots')->cascadeOnDelete();

            $table->string('group_chat_id');
            $table->bigInteger('group_message_id');

            $table->string('user_chat_id');
            $table->string('user_name')->nullable();
            $table->string('user_username')->nullable();

            $table->timestamps();

            // Reply kelganda shu juftlik bo'yicha qidiriladi.
            $table->unique(['group_chat_id', 'group_message_id']);
            $table->index('user_chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
    }
};
