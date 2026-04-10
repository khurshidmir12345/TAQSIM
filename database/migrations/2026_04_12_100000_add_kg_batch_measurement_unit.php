<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Partiya o‘lchovi sifatida KG (ingredientdagi `kg` dan alohida kod: kg_batch).
     */
    public function up(): void
    {
        if (DB::table('measurement_units')->where('code', 'kg_batch')->exists()) {
            return;
        }

        DB::table('measurement_units')
            ->where('type', 'batch')
            ->where('sort_order', '>=', 2)
            ->increment('sort_order');

        DB::table('measurement_units')->insert([
            'id'           => (string) Str::uuid(),
            'type'         => 'batch',
            'code'         => 'kg_batch',
            'name_uz'      => 'KG (partiya)',
            'name_uz_cyrl' => 'КГ (партия)',
            'name_ru'      => 'КГ (партия)',
            'name_kk'      => 'КГ (партия)',
            'name_ky'      => 'КГ (партия)',
            'name_tr'      => 'KG (parti)',
            'example_uz'   => 'Bitta partiyada masalan 25 kg xamir — shu KG bo‘yicha hisoblanganda chiqim donada bo‘ladi.',
            'example_ru'   => 'Например, 25 кг теста в партии — выход в штуках указывается отдельно.',
            'icon'         => '⚖️',
            'sort_order'   => 2,
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('measurement_units')->where('code', 'kg_batch')->delete();

        DB::table('measurement_units')
            ->where('type', 'batch')
            ->where('sort_order', '>', 2)
            ->decrement('sort_order');
    }
};
