<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foydalanuvchi obunalari (tarix bilan).
     * Joriy obuna `is_current = true` bo'lgan yozuv.
     * Holat (status) timestamps asosida dinamik ham hisoblanadi
     * (trialing → active → grace → expired).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->nullable()
                ->constrained('subscription_plans')->nullOnDelete();
            $table->string('plan_code', 32)->nullable(); // snapshot

            $table->string('status', 16)->default('trialing'); // trialing|active|grace|expired|cancelled
            $table->boolean('is_current')->default(true);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_current']);
            $table->index('status');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
