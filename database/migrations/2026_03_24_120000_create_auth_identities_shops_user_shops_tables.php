<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32)->comment('telegram | google | phone');
            $table->string('provider_subject', 191)->comment('Telegram ID, Google sub yoki E.164 telefon');
            $table->json('metadata')->nullable()->comment('username, avatar_url va h.k.');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_subject']);
            $table->index('user_id');
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_shops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->enum('user_type', ['owner', 'seller']);
            $table->timestamps();

            $table->unique(['user_id', 'shop_id']);
            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_shops');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('auth_identities');
    }
};
