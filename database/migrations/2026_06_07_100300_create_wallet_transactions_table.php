<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Balans harakatlari (ledger / balansHistory).
     * `amount` musbat = kirim (credit), manfiy = chiqim (debit).
     * `balance_after` — operatsiyadan keyingi balans (audit uchun).
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 24); // topup | subscription_charge | refund | adjustment
            $table->decimal('amount', 15, 2);          // +credit / -debit
            $table->decimal('balance_after', 15, 2);
            $table->string('currency_code', 8)->default('UZS');

            $table->string('description')->nullable();
            $table->foreignUuid('order_id')->nullable()
                ->constrained('orders')->nullOnDelete();
            $table->string('status', 16)->default('completed'); // pending|completed|failed
            $table->json('meta')->nullable();
            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete(); // admin (qo'lda kredit)

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
