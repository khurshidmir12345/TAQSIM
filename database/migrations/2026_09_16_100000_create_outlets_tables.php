<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Do'konlar (tarqatish nuqtalari) va ular bilan hisob-kitob daftari.
 *
 * `outlets` — do'kon kartochkasi (nom, manzil, joylashuv, rasm, telefonlar).
 * `outlet_entries` — daftar: bitta qator = bitta amal:
 *   delivery — mahsulot berildi (summa = mahsulotlar jami),
 *   return   — sotilmagan mahsulot qaytdi (summa = qaytgan mahsulotlar jami),
 *   payment  — do'kon pul to'ladi.
 * Balans = Σ delivery − Σ return − Σ payment (musbat = do'kon qarzdor).
 * `outlet_entry_items` — delivery/return qatoridagi mahsulotlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('image_path')->nullable();
            /** ["+998901234567", ...] — 3 tagacha. */
            $table->json('phones')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'name']);
        });

        Schema::create('outlet_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            /** delivery | return | payment */
            $table->string('type', 16);
            $table->date('date');
            $table->decimal('amount', 15, 2);
            /** Mahsulot berilganda darhol to'langan pul — shu delivery'ga bog'langan payment qatori. */
            $table->uuid('related_entry_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'outlet_id', 'date']);
            $table->index(['shop_id', 'type', 'date']);
        });

        Schema::create('outlet_entry_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outlet_entry_id')->constrained('outlet_entries')->cascadeOnDelete();
            $table->foreignUuid('bread_category_id')->constrained('bread_categories');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::table('shops', function (Blueprint $table) {
            // Do'kon to'lovi kassaga kirim bo'lib tushsinmi.
            $table->boolean('cash_track_outlet_payments')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('cash_track_outlet_payments');
        });
        Schema::dropIfExists('outlet_entry_items');
        Schema::dropIfExists('outlet_entries');
        Schema::dropIfExists('outlets');
    }
};
