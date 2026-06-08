<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Xaridlar / billing buyurtmalari: obuna sotib olish va balans to'ldirish.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 32)->unique();

            $table->string('type', 16);   // subscription | topup
            $table->string('status', 16)->default('pending'); // pending|paid|failed|cancelled

            $table->foreignUuid('plan_id')->nullable()
                ->constrained('subscription_plans')->nullOnDelete();
            $table->string('plan_code', 32)->nullable();

            $table->decimal('amount_usd', 10, 2)->nullable();
            $table->decimal('amount_local', 15, 2)->default(0);
            $table->string('currency_code', 8)->default('UZS');
            $table->decimal('exchange_rate', 15, 4)->nullable();

            $table->string('payment_method', 24)->nullable(); // balance|manual|payme|click
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
