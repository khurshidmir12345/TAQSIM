<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BATCH_CODES = [
        'QOP', 'BLOK', 'BOLIM', 'TOPLAM', 'QUTI', 'KILOMETR', 'TONNA', 'KUB',
    ];

    public function up(): void
    {
        $this->ensureBatchUnits();

        if (! Schema::hasColumn('recipes', 'bread_category_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->foreignUuid('bread_category_id')
                    ->nullable()
                    ->after('shop_id')
                    ->constrained('bread_categories')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('recipes', 'measurement_unit_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->foreignUuid('measurement_unit_id')
                    ->nullable()
                    ->after('output_quantity')
                    ->constrained('measurement_units')
                    ->restrictOnDelete();
            });
        }

        $qopId = DB::table('measurement_units')->where('code', 'QOP')->value('id');

        if (Schema::hasTable('recipe_bread_categories')) {
            $pairs = DB::table('recipe_bread_categories')
                ->selectRaw('recipe_id, MIN(bread_category_id) as bc_id')
                ->groupBy('recipe_id')
                ->get();

            foreach ($pairs as $row) {
                DB::table('recipes')
                    ->where('id', $row->recipe_id)
                    ->update(['bread_category_id' => $row->bc_id]);
            }
        }

        if ($qopId) {
            DB::table('recipes')->whereNull('measurement_unit_id')->update(['measurement_unit_id' => $qopId]);
        }

        $orphanIds = DB::table('recipes')->whereNull('bread_category_id')->pluck('id');
        if ($orphanIds->isNotEmpty()) {
            foreach ($orphanIds as $orphanId) {
                $this->reassignProductionsAwayFromOrphanRecipe((string) $orphanId);
            }
            DB::table('recipe_ingredients')->whereIn('recipe_id', $orphanIds)->delete();
            DB::table('recipes')->whereIn('id', $orphanIds)->delete();
        }

        $dupes = DB::table('recipes')
            ->select('shop_id', 'bread_category_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('bread_category_id')
            ->groupBy('shop_id', 'bread_category_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($dupes as $d) {
            $ids = DB::table('recipes')
                ->where('shop_id', $d->shop_id)
                ->where('bread_category_id', $d->bread_category_id)
                ->orderBy('id')
                ->pluck('id');
            $keepId = $ids->shift();
            foreach ($ids as $rid) {
                DB::table('productions')
                    ->where('recipe_id', $rid)
                    ->update(['recipe_id' => $keepId]);
                DB::table('recipe_ingredients')->where('recipe_id', $rid)->delete();
                DB::table('recipes')->where('id', $rid)->delete();
            }
        }

        Schema::dropIfExists('recipe_bread_categories');

        if (! $this->uniqueShopBreadCategoryExistsOnRecipes()) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->unique(['shop_id', 'bread_category_id']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            if ($this->mysqlColumnIsNullable('recipes', 'bread_category_id')) {
                DB::statement('ALTER TABLE recipes MODIFY bread_category_id CHAR(36) NOT NULL');
            }
            if ($this->mysqlColumnIsNullable('recipes', 'measurement_unit_id')) {
                DB::statement('ALTER TABLE recipes MODIFY measurement_unit_id CHAR(36) NOT NULL');
            }
        }

        $this->purgeDisallowedBatchUnits();
    }

    public function down(): void
    {
        throw new \RuntimeException('2026_04_05_100000_recipes_single_category_and_batch_units_cleanup cannot be reversed safely.');
    }

    /**
     * (shop_id, bread_category_id) bo‘yicha unique indeks bor-yo‘qligi (nomidan mustaqil).
     */
    private function uniqueShopBreadCategoryExistsOnRecipes(): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $rows = DB::select(
                'SELECT index_name, COUNT(DISTINCT column_name) AS col_cnt
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ?
                 AND non_unique = 0 AND index_name != \'PRIMARY\'
                 AND column_name IN (\'shop_id\', \'bread_category_id\')
                 GROUP BY index_name
                 HAVING col_cnt = 2',
                ['recipes']
            );

            return count($rows) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'recipes'");
            foreach ($rows as $r) {
                $sql = (string) ($r->sql ?? '');
                if ($sql !== '' && str_contains($sql, 'shop_id') && str_contains($sql, 'bread_category_id')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function mysqlColumnIsNullable(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );

        return $row && ($row->IS_NULLABLE ?? '') === 'YES';
    }

    /**
     * Yetim retseptga bog‘langan ishlab chiqarish yozuvlarini boshqa retseptga o‘tkazadi yoki o‘chiradi.
     * Pivot mavjud bo‘lsa — pivot orqali; aks holda `recipes` jadvalidagi bir xil turdagi retseptga.
     */
    private function reassignProductionsAwayFromOrphanRecipe(string $orphanRecipeId): void
    {
        $prods = DB::table('productions')->where('recipe_id', $orphanRecipeId)->get();
        foreach ($prods as $p) {
            $target = null;

            if (Schema::hasTable('recipe_bread_categories')) {
                $target = DB::table('recipe_bread_categories as rbc')
                    ->join('recipes as r', 'r.id', '=', 'rbc.recipe_id')
                    ->where('r.shop_id', $p->shop_id)
                    ->where('rbc.bread_category_id', $p->bread_category_id)
                    ->where('rbc.recipe_id', '!=', $orphanRecipeId)
                    ->orderBy('rbc.recipe_id')
                    ->value('rbc.recipe_id');
            } else {
                $target = DB::table('recipes')
                    ->where('shop_id', $p->shop_id)
                    ->where('bread_category_id', $p->bread_category_id)
                    ->whereNotNull('bread_category_id')
                    ->where('id', '!=', $orphanRecipeId)
                    ->orderBy('id')
                    ->value('id');
            }

            if ($target) {
                DB::table('productions')->where('id', $p->id)->update(['recipe_id' => $target]);
            } else {
                DB::table('productions')->where('id', $p->id)->delete();
            }
        }
    }

    private function ensureBatchUnits(): void
    {
        $now = now();
        $new = [
            [
                'type' => 'batch', 'code' => 'TOPLAM', 'icon' => '📚', 'sort_order' => 5,
                'name_uz' => 'To\'plam', 'name_uz_cyrl' => 'Тўплам', 'name_ru' => 'Комплект',
                'name_kk' => 'Топлам', 'name_ky' => 'Топтом', 'name_tr' => 'Set',
                'example_uz' => 'Bir to\'plam mahsulot — masalan, 12 ta qadoqli set',
                'example_ru' => 'Комплект продукции, например набор из 12 штук',
            ],
            [
                'type' => 'batch', 'code' => 'KILOMETR', 'icon' => '🛣️', 'sort_order' => 6,
                'name_uz' => 'Kilometr', 'name_uz_cyrl' => 'Километр', 'name_ru' => 'Километр',
                'name_kk' => 'Километр', 'name_ky' => 'Километр', 'name_tr' => 'Kilometre',
                'example_uz' => 'Yetkazib berish yoki chiziqli o\'lchov talab qilganda',
                'example_ru' => 'Доставка или линейный учёт',
            ],
            [
                'type' => 'batch', 'code' => 'TONNA', 'icon' => '⚓', 'sort_order' => 7,
                'name_uz' => 'Tonna', 'name_uz_cyrl' => 'Тонна', 'name_ru' => 'Тонна',
                'name_kk' => 'Тонна', 'name_ky' => 'Тонна', 'name_tr' => 'Ton',
                'example_uz' => 'Katta hajm — tonna bo\'yicha hisoblash',
                'example_ru' => 'Крупные объёмы — учёт в тоннах',
            ],
            [
                'type' => 'batch', 'code' => 'KUB', 'icon' => '📐', 'sort_order' => 8,
                'name_uz' => 'Kubometr', 'name_uz_cyrl' => 'Куб метр', 'name_ru' => 'Куб. метр',
                'name_kk' => 'Куб метр', 'name_ky' => 'Куб метр', 'name_tr' => 'm³',
                'example_uz' => 'Hajm bo\'yicha — suv, shag\'al, qum va hokazo',
                'example_ru' => 'Объёмный учёт',
            ],
        ];

        foreach ($new as $row) {
            $exists = DB::table('measurement_units')->where('code', $row['code'])->exists();
            if (! $exists) {
                DB::table('measurement_units')->insert(array_merge($row, [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    private function purgeDisallowedBatchUnits(): void
    {
        $badIds = DB::table('measurement_units')
            ->where('type', 'batch')
            ->whereNotIn('code', self::BATCH_CODES)
            ->pluck('id');

        if ($badIds->isEmpty()) {
            return;
        }

        $qopId = DB::table('measurement_units')->where('code', 'QOP')->value('id');

        DB::table('shop_measurement_units')->whereIn('measurement_unit_id', $badIds)->delete();

        if ($qopId) {
            $shopsNeedingQop = DB::table('shops')
                ->whereNotExists(function ($q) use ($qopId) {
                    $q->selectRaw('1')
                        ->from('shop_measurement_units as smu')
                        ->whereColumn('smu.shop_id', 'shops.id')
                        ->where('smu.measurement_unit_id', $qopId);
                })
                ->pluck('id');

            $ts = now();
            foreach ($shopsNeedingQop as $shopId) {
                DB::table('shop_measurement_units')->insert([
                    'shop_id' => $shopId,
                    'measurement_unit_id' => $qopId,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);
            }

            DB::table('recipes')->whereIn('measurement_unit_id', $badIds)->update(['measurement_unit_id' => $qopId]);
        }

        DB::table('measurement_units')->whereIn('id', $badIds)->delete();
    }
};
