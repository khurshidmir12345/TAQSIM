<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Profil ekrani "Aloqa" bo'limi uchun default SystemLink yozuvlari.
 *
 * Mobil ilova `GET /api/v1/system-links` orqali faqat `is_active = true`
 * yozuvlarni oladi. Shuning uchun bu yerda placeholder URL bilan
 * `is_active = false` qilib yaratamiz — admin paneldan haqiqiy URL kiritib
 * faollashtirilgach, mobilda ko'rinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'type' => 'telegram',
                'name' => 'Telegram kanali',
                'url'  => 'https://t.me/taqseem_rasmiy',
            ],
            [
                'type' => 'instagram',
                'name' => 'Instagram sahifasi',
                'url'  => 'https://instagram.com/taqseem.uz',
            ],
            [
                'type' => 'youtube',
                'name' => 'YouTube kanali',
                'url'  => 'https://youtube.com/@taqseem',
            ],
            [
                'type' => 'support',
                'name' => 'Texnik yordam',
                'url'  => 'https://t.me/taqseem_support',
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('system_links')->where('type', $row['type'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('system_links')->insert([
                'id'         => (string) Str::uuid(),
                'name'       => $row['name'],
                'type'       => $row['type'],
                'url'        => $row['url'],
                'is_active'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_links')
            ->whereIn('type', ['telegram', 'instagram', 'youtube', 'support'])
            ->delete();
    }
};
