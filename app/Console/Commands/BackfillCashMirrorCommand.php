<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\CashMirrorService;
use Illuminate\Console\Command;

/**
 * Mavjud mahsulot chiqimi va vozvratlarni kassaga ko'chiradi.
 *
 * Observer faqat yangi yozuvlarda ishlaydi, shuning uchun kassa qo'shilgandan
 * keyin bir marta ishga tushirilishi kerak — aks holda do'kon sozlamasi yoqiq
 * turgani holda kassada eski kunlar bo'sh ko'rinardi.
 *
 * Qayta ishga tushirish xavfsiz: yozuv bor bo'lsa yangilanadi, sozlama
 * o'chiq bo'lsa o'chiriladi.
 */
class BackfillCashMirrorCommand extends Command
{
    protected $signature = 'cash:backfill {--shop= : Faqat bitta do\'kon uchun}';

    protected $description = 'Mavjud chiqim va vozvratlarni kassa daftariga ko\'chirish';

    public function handle(CashMirrorService $mirror): int
    {
        $query = Shop::query();

        if ($shopId = $this->option('shop')) {
            $query->whereKey($shopId);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('Do\'kon topilmadi.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(50, function ($shops) use ($mirror, $bar): void {
            foreach ($shops as $shop) {
                $mirror->resyncShop($shop);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("{$total} ta do'kon kassasi yangilandi.");

        return self::SUCCESS;
    }
}
