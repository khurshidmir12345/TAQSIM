<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bitta partiyadan bir nechta mahsulot.
 *
 * - `recipe_outputs`: retseptning 1 partiyasidan qaysi mahsulot nechta chiqadi
 *   (odatiy taqsimot). `recipes.bread_category_id` asosiy (birinchi) mahsulot,
 *   `recipes.output_quantity` esa jami chiqim bo'lib qoladi — eski ilova
 *   versiyalari shu maydonlarni o'qiydi.
 * - `productions.batch_id`: bir partiyadan chiqqan mahsulotlar alohida
 *   `productions` qatorlari, umumiy `batch_id` bilan. Har qator partiyaning
 *   o'z ulushini (batch_count, ingredient_cost, flour_used_kg) saqlaydi —
 *   shunda hisobot, kassa va vozvrat o'zgarishsiz to'g'ri ishlaydi.
 * - `recipes.kind`: `single` — bitta mahsulot retsepti (har mahsulotga bittadan),
 *   `mix` — aralash partiya: o'z xom ashyo tarkibi, istalgan mahsulotlar
 *   (o'z retsepti bor bo'lsa ham). Aralashda `bread_category_id` NULL — shuning
 *   uchun (shop_id, bread_category_id) unique indeksi unga xalaqit bermaydi,
 *   eski ilova esa aralash partiyani mahsulot retsepti deb adashtirmaydi.
 * - `batch_count` aniqligi 4 xonagacha — ulushlar yig'indisi partiyaga teng
 *   bo'lishi uchun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignUuid('bread_category_id')->constrained('bread_categories')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['recipe_id', 'bread_category_id']);
        });

        $now = now();
        DB::table('recipes')
            ->whereNotNull('bread_category_id')
            ->orderBy('id')
            ->chunk(500, function ($recipes) use ($now) {
                $rows = [];
                foreach ($recipes as $r) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'recipe_id' => $r->id,
                        'bread_category_id' => $r->bread_category_id,
                        'quantity' => max(1, (int) $r->output_quantity),
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('recipe_outputs')->insert($rows);
            });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('kind', 16)->default('single')->after('shop_id');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->uuid('bread_category_id')->nullable()->change();
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('recipe_id')->index();
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('batch_count', 12, 4)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('batch_count', 10, 2)->default(1)->change();
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });

        // Aralash partiyalarda asosiy mahsulot yo'q — orqaga qaytishda ular o'chadi.
        DB::table('recipes')->where('kind', 'mix')->delete();

        Schema::table('recipes', function (Blueprint $table) {
            $table->uuid('bread_category_id')->nullable(false)->change();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::dropIfExists('recipe_outputs');
    }
};
