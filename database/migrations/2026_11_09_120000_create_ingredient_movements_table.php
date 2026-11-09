<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xom ashyo ombori.
 *
 * - `ingredient_movements`: har bir harakat — kirim (xarid), sarf (mahsulot
 *   chiqimi), tuzatish (sanash). Miqdor ishorali: kirim +, sarf −.
 * - `ingredients.stock_quantity`: harakatlar yig'indisi (tez o'qish uchun).
 * - `ingredients.min_stock`, `low_notified_at`: «kam qoldi» ogohlantirishi.
 * - `shops.inventory_started_at`: ombor yuritila boshlangan payt. Undan keyingi
 *   chiqimlarda xom ashyo puli kassaga yozilmaydi — pul xarid paytida yozilgan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignUuid('production_id')->nullable()->constrained('productions')->cascadeOnDelete();
            $table->string('note', 255)->nullable();
            $table->date('date');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'type', 'date']);
            $table->index(['ingredient_id']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('stock_quantity', 15, 3)->default(0);
            // Ixtiyoriy: shundan kam qolsa ogohlantirish. NULL — sarf
            // tezligidan avtomatik (2 kunga yetmasa).
            $table->decimal('min_stock', 15, 3)->nullable();
            // «Kam qoldi» xabari yuborilgan payt; qoldiq tiklansa tozalanadi.
            $table->timestamp('low_notified_at')->nullable();
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('inventory_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_movements');
        Schema::table('ingredients', fn (Blueprint $t) => $t->dropColumn(['stock_quantity', 'min_stock', 'low_notified_at']));
        Schema::table('shops', fn (Blueprint $t) => $t->dropColumn('inventory_started_at'));
    }
};
