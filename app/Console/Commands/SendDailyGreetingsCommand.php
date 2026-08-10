<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyGreetings;
use Illuminate\Console\Command;

/**
 * Kunlik tilakni navbatga qo'yadi. Scheduler har kuni ertalab chaqiradi,
 * qo'lda sinash uchun ham ishlatiladi: `php artisan notifications:daily-greeting`.
 */
class SendDailyGreetingsCommand extends Command
{
    protected $signature = 'notifications:daily-greeting {--now : Navbatsiz, shu yerda bajarish}';

    protected $description = 'Kunlik tilak bildirishnomasini yuborish';

    public function handle(): int
    {
        if ($this->option('now')) {
            SendDailyGreetings::dispatchSync();
            $this->info('Kunlik tilak yuborildi.');

            return self::SUCCESS;
        }

        SendDailyGreetings::dispatch();
        $this->info('Kunlik tilak navbatga qo\'yildi.');

        return self::SUCCESS;
    }
}
