<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_auth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_token', 64)->unique();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('auth_token')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('telegram_chat_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_auth_sessions');
    }
};
