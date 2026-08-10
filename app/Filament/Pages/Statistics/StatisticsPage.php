<?php

namespace App\Filament\Pages\Statistics;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Statistika sahifalarining umumiy asosi.
 *
 * Sahifa darajasida filtr yo'q — davrni grafikning o'zidagi "Kunlik / Oylik"
 * tanlovi belgilaydi. Kunlik oxirgi 30 kunni, oylik oxirgi 12 oyni ko'rsatadi.
 */
abstract class StatisticsPage extends BaseDashboard
{
    protected static ?string $navigationGroup = 'Statistika';

    /** Grafik butun kenglikni egallasin. */
    public function getColumns(): int|string|array
    {
        return 1;
    }
}
