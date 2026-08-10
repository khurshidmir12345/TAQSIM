<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kassa daftari.
 *
 * Qo'lda kiritilgan kirimlar va asosiy sahifadagi amallardan (mahsulot
 * chiqimi, vozvrat) avtomatik ko'chirilgan yozuvlar shu yerda turadi.
 * Qo'lda kiritilgan xarajatlar avvalgidek `expenses` jadvalida qoladi —
 * foydalanuvchilar qo'lidagi eski ilova hamon o'sha endpointga yozadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();

            /** income | expense */
            $table->string('type', 16);

            /** manual | production | return */
            $table->string('source', 16)->default('manual');

            /** Avtomatik yozuvda — manba yozuvning id'si. */
            $table->uuid('source_id')->nullable();

            $table->string('category', 64)->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('date');

            $table->foreignUuid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['shop_id', 'date']);
            $table->index(['shop_id', 'type', 'date']);

            // Manba yozuvi o'zgarganda unga tegishli qatorlarni topish uchun.
            $table->index(['source', 'source_id']);
        });

        Schema::table('shops', function (Blueprint $table) {
            // Kunlik mahsulot chiqimi foydasi kassaga kirim/chiqim bo'lib tushsinmi.
            $table->boolean('cash_track_production')->default(true);

            // Vozvrat summasi kassaga chiqim bo'lib tushsinmi.
            $table->boolean('cash_track_returns')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['cash_track_production', 'cash_track_returns']);
        });

        Schema::dropIfExists('cash_transactions');
    }
};
