<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class SyncExchangeRate extends Command
{
    protected $signature = 'billing:sync-exchange';

    protected $description = 'USD→UZS kursini CBU (Markaziy bank) API\'dan yangilaydi';

    public function handle(ExchangeRateService $exchange): int
    {
        $rate = $exchange->syncFromCbu();

        if ($rate === null) {
            $this->error('Kursni yangilab bo\'lmadi (CBU javob bermadi).');

            return self::FAILURE;
        }

        $this->info("Kurs yangilandi: 1 USD = {$rate} UZS");

        return self::SUCCESS;
    }
}
