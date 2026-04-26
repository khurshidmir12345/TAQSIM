<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mahsulotlar uchun o'lchov birligi (Dona, Kilogram, Litr, Metr).
     * Xom ashyodagi kabi ko'p tilli `measurement_units` jadvalidan foydalanamiz —
     * shu 4 kod (`ta`, `kg`, `l`, `m`) qayta ishlatiladi (DRY).
     */
    private const ALLOWED_CODES = ['ta', 'kg', 'l', 'm'];

    private const FALLBACK_CODE = 'ta';

    public function up(): void
    {
        Schema::table('bread_categories', function (Blueprint $table) {
            $table->foreignUuid('measurement_unit_id')
                ->nullable()
                ->after('currency_id')
                ->constrained('measurement_units')
                ->restrictOnDelete();

            $table->index('measurement_unit_id', 'bc_measurement_unit_id_idx');
        });

        $idByCode = DB::table('measurement_units')
            ->where('type', 'ingredient')
            ->whereIn('code', self::ALLOWED_CODES)
            ->pluck('id', 'code');

        $fallbackId = $idByCode[self::FALLBACK_CODE] ?? null;
        if ($fallbackId === null) {
            return;
        }

        DB::table('bread_categories')
            ->whereNull('measurement_unit_id')
            ->update(['measurement_unit_id' => $fallbackId]);
    }

    public function down(): void
    {
        Schema::table('bread_categories', function (Blueprint $table) {
            $table->dropIndex('bc_measurement_unit_id_idx');
            $table->dropConstrainedForeignId('measurement_unit_id');
        });
    }
};
