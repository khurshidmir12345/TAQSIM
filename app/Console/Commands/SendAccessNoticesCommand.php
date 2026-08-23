<?php

namespace App\Console\Commands;

use App\Jobs\SendAccessNotices;
use Illuminate\Console\Command;

/**
 * Muddat ogohlantirishlarini navbatga qo'yadi. Scheduler har kuni chaqiradi,
 * qo'lda sinash uchun: `php artisan access:notices --now`.
 */
class SendAccessNoticesCommand extends Command
{
    protected $signature = 'access:notices {--now : Navbatsiz, shu yerda bajarish}';

    protected $description = 'Kirish muddati haqida Telegram orqali ogohlantirish';

    public function handle(): int
    {
        if ($this->option('now')) {
            SendAccessNotices::dispatchSync();
            $this->info('Ogohlantirishlar yuborildi.');

            return self::SUCCESS;
        }

        SendAccessNotices::dispatch();
        $this->info('Ogohlantirishlar navbatga qo\'yildi.');

        return self::SUCCESS;
    }
}
