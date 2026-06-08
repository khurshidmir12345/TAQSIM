<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Xodim (seller) obunalari — owner obunasidan (subscriptions) ALOHIDA saqlanadi.
     *
     * - Sellerga umuman trial berilmaydi.
     * - Seller faqat obunasi `active` bo'lsa ishlay oladi.
     * - Bepul o'rin (owner tarifi limiti ichida): is_paid_seat=false, ends_at=null
     *   (kirishi owner obunasiga bog'liq).
     * - Pulli o'rin (limitdan oshganda): is_paid_seat=true, oylik to'lov, ends_at bilan.
     */
    public function up(): void
    {
        Schema::create('seller_subs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('owner_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // active | past_due | expired
            $table->string('status', 16)->default('active');
            $table->boolean('is_paid_seat')->default(false);
            $table->decimal('price_usd', 8, 2)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'shop_id']);
            $table->index('status');
            $table->index('ends_at');
        });

        // Eski "seat" maydonlari endi seller_subs ichida — user_shops'dan olib tashlanadi.
        // (permissions va invited_by pivotda qoladi.)
        Schema::table('user_shops', function (Blueprint $table) {
            $table->dropColumn(['seat_status', 'is_paid_seat', 'seat_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_shops', function (Blueprint $table) {
            $table->string('seat_status', 16)->default('active')->after('permissions');
            $table->boolean('is_paid_seat')->default(false)->after('seat_status');
            $table->timestamp('seat_ends_at')->nullable()->after('is_paid_seat');
        });

        Schema::dropIfExists('seller_subs');
    }
};
