<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Yangi partiya birliklarini qo'shadi: dona_batch, l_batch, m_batch, qozon.
     *
     * Maqsad: novvoyxonadan tashqari boshqa ishlab chiqaruvchilar (cex, ustaxona,
     * sex, yog' quyish, pishirish va h.k.) ham retseptda partiya hajmini o'z
     * birligida ko'rsatishi uchun.
     *
     * - `dona_batch` — partiyada N dona xomashyo (masalan: 10 dona idish).
     * - `l_batch`    — litr bo'yicha partiya (masalan: 50 l yog').
     * - `m_batch`    — metr bo'yicha partiya (masalan: 20 m kanvas).
     * - `qozon`      — pishirish/qovurish jarayonidagi qozon.
     */
    public function up(): void
    {
        $units = [
            [
                'code'         => 'dona_batch',
                'icon'         => '🔢',
                'name_uz'      => 'Dona (partiya)',
                'name_uz_cyrl' => 'Дона (партия)',
                'name_ru'      => 'Шт. (партия)',
                'name_kk'      => 'Дана (партия)',
                'name_ky'      => 'Даана (партия)',
                'name_tr'      => 'Adet (parti)',
                'example_uz'   => 'Bir partiyada N dona xomashyo (masalan: 10 dona idish/forma).',
                'example_ru'   => 'В одной партии — N штук материала (например 10 форм).',
            ],
            [
                'code'         => 'l_batch',
                'icon'         => '💧',
                'name_uz'      => 'Litr (partiya)',
                'name_uz_cyrl' => 'Литр (партия)',
                'name_ru'      => 'Литр (партия)',
                'name_kk'      => 'Литр (партия)',
                'name_ky'      => 'Литр (партия)',
                'name_tr'      => 'Litre (parti)',
                'example_uz'   => 'Bir partiyada N litr suyuqlik (masalan: 50 l yog\').',
                'example_ru'   => 'В партии — N литров жидкости (например 50 л масла).',
            ],
            [
                'code'         => 'm_batch',
                'icon'         => '📏',
                'name_uz'      => 'Metr (partiya)',
                'name_uz_cyrl' => 'Метр (партия)',
                'name_ru'      => 'Метр (партия)',
                'name_kk'      => 'Метр (партия)',
                'name_ky'      => 'Метр (партия)',
                'name_tr'      => 'Metre (parti)',
                'example_uz'   => 'Bir partiyada N metr material (masalan: 20 m kanvas).',
                'example_ru'   => 'В партии — N метров материала (например 20 м ткани).',
            ],
            [
                'code'         => 'qozon',
                'icon'         => '🍲',
                'name_uz'      => 'Qozon',
                'name_uz_cyrl' => 'Қозон',
                'name_ru'      => 'Казан',
                'name_kk'      => 'Қазан',
                'name_ky'      => 'Казан',
                'name_tr'      => 'Kazan',
                'example_uz'   => 'Oshxona/cex: bitta qozon (masalan plov, sho\'rva, manti).',
                'example_ru'   => 'Кухня/цех: один казан (плов, шурпа, манты).',
            ],
        ];

        $maxSort = (int) DB::table('measurement_units')
            ->where('type', 'batch')
            ->max('sort_order');

        $now = now();
        foreach ($units as $i => $u) {
            if (DB::table('measurement_units')->where('code', $u['code'])->exists()) {
                continue;
            }
            DB::table('measurement_units')->insert([
                'id'           => (string) Str::uuid(),
                'type'         => 'batch',
                'code'         => $u['code'],
                'name_uz'      => $u['name_uz'],
                'name_uz_cyrl' => $u['name_uz_cyrl'],
                'name_ru'      => $u['name_ru'],
                'name_kk'      => $u['name_kk'],
                'name_ky'      => $u['name_ky'],
                'name_tr'      => $u['name_tr'],
                'example_uz'   => $u['example_uz'],
                'example_ru'   => $u['example_ru'],
                'icon'         => $u['icon'],
                'sort_order'   => $maxSort + 1 + $i,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('measurement_units')
            ->whereIn('code', ['dona_batch', 'l_batch', 'm_batch', 'qozon'])
            ->delete();
    }
};
