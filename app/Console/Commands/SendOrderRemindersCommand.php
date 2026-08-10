<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderReminders;
use Illuminate\Console\Command;

/**
 * Bugungi zakaz eslatmasini navbatga qo'yadi. Scheduler erta tongda chaqiradi,
 * qo'lda sinash uchun ham ishlatiladi: `php artisan notifications:order-reminder`.
 */
class SendOrderRemindersCommand extends Command
{
    protected $signature = 'notifications:order-reminder {--now : Navbatsiz, shu yerda bajarish}';

    protected $description = 'Bugungi zakazlar haqida eslatma yuborish';

    public function handle(): int
    {
        if ($this->option('now')) {
            SendOrderReminders::dispatchSync();
            $this->info('Zakaz eslatmasi yuborildi.');

            return self::SUCCESS;
        }

        SendOrderReminders::dispatch();
        $this->info('Zakaz eslatmasi navbatga qo\'yildi.');

        return self::SUCCESS;
    }
}
