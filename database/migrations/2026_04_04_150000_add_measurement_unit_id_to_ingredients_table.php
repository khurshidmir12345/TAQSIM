<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALLOWED_CODES = ['kg', 'l', 'm', 'ta'];

    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignUuid('measurement_unit_id')
                ->nullable()
                ->after('unit')
                ->constrained('measurement_units')
                ->restrictOnDelete();
        });

        $idByCode = DB::table('measurement_units')
            ->where('type', 'ingredient')
            ->whereIn('code', self::ALLOWED_CODES)
            ->pluck('id', 'code');

        $fallbackKg = $idByCode['kg'] ?? null;
        if ($fallbackKg === null) {
            return;
        }

        $legacyToCode = [
            'kg' => 'kg',
            'gram' => 'kg',
            'litr' => 'l',
            'dona' => 'ta',
            'metr' => 'm',
        ];

        $rows = DB::table('ingredients')->select('id', 'unit')->get();
        foreach ($rows as $row) {
            $u = strtolower((string) $row->unit);
            $code = $legacyToCode[$u] ?? 'kg';
            $muId = $idByCode[$code] ?? $fallbackKg;
            DB::table('ingredients')->where('id', $row->id)->update(['measurement_unit_id' => $muId]);
        }

        DB::table('measurement_units')
            ->where('type', 'ingredient')
            ->whereIn('code', ['g', 'ml'])
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('measurement_unit_id');
        });

        DB::table('measurement_units')
            ->where('type', 'ingredient')
            ->whereIn('code', ['g', 'ml'])
            ->update(['is_active' => true]);
    }
};
