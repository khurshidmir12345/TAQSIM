<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `measurement_units.code` — qisqa, kichik harf (kg, l, ta, qop, m3, ...).
     */
    private const MAP = [
        'KG' => 'kg',
        'G' => 'g',
        'DONA' => 'ta',
        'LITR' => 'l',
        'ML' => 'ml',
        'METR' => 'm',
        'QOP' => 'qop',
        'BLOK' => 'blok',
        'BOLIM' => 'bolim',
        'QUTI' => 'quti',
        'TOPLAM' => 'toplam',
        'KILOMETR' => 'km',
        'TONNA' => 'ton',
        'KUB' => 'm3',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('measurement_units')->where('code', $old)->update(['code' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('measurement_units')->where('code', $new)->update(['code' => $old]);
        }
    }
};
