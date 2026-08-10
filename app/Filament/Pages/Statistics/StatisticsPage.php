<?php

namespace App\Filament\Pages\Statistics;

use App\Services\Admin\UserStatisticsService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Statistika sahifalarining umumiy filtri: tayyor davr tanlovi, kesim
 * (kunlik/oylik) va ixtiyoriy sana oralig'i.
 *
 * Har bir sahifa faqat o'z vidjetlarini beradi — shunda bitta uzun
 * dashboard o'rniga mavzular bo'yicha ajratilgan ekranlar chiqadi.
 */
abstract class StatisticsPage extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationGroup = 'Statistika';

    /** Tayyor davrlar — admin qo'lda sana tanlamasin. */
    public const RANGES = [
        'today' => 'Bugun',
        '7d' => 'Oxirgi 7 kun',
        '30d' => 'Oxirgi 30 kun',
        'this_month' => 'Shu oy',
        'last_month' => 'O\'tgan oy',
        '6m' => 'Oxirgi 6 oy',
        '12m' => 'Oxirgi 12 oy',
        'custom' => 'Ixtiyoriy',
    ];

    /** Uzoq davrlar oylik kesimda o'qilishi qulayroq. */
    private const MONTHLY_RANGES = ['6m', '12m'];

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->schema([
                    Select::make('range')
                        ->label('Davr')
                        ->options(self::RANGES)
                        ->default('30d')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if ($state === null || $state === 'custom') {
                                return;
                            }

                            [$from, $to] = self::resolveRange($state);

                            $set('from', $from);
                            $set('to', $to);
                            $set('period', in_array($state, self::MONTHLY_RANGES, true)
                                ? UserStatisticsService::MONTHLY
                                : UserStatisticsService::DAILY);
                        }),

                    Select::make('period')
                        ->label('Kesim')
                        ->options([
                            UserStatisticsService::DAILY => 'Kunlik',
                            UserStatisticsService::MONTHLY => 'Oylik',
                        ])
                        ->default(UserStatisticsService::DAILY)
                        ->selectablePlaceholder(false)
                        ->live(),

                    DatePicker::make('from')
                        ->label('Boshlanish')
                        ->native(false)
                        ->default(self::resolveRange('30d')[0])
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('range', 'custom')),

                    DatePicker::make('to')
                        ->label('Tugash')
                        ->native(false)
                        ->default(self::resolveRange('30d')[1])
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('range', 'custom')),
                ])
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4]),
        ]);
    }

    /**
     * Tanlangan davrni sana oralig'iga aylantiradi.
     *
     * @return array{0:string,1:string}
     */
    public static function resolveRange(string $range): array
    {
        $today = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();

        return match ($range) {
            'today' => [$today->toDateString(), $today->toDateString()],
            '7d' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'this_month' => [$today->startOfMonth()->toDateString(), $today->toDateString()],
            'last_month' => [
                $today->subMonth()->startOfMonth()->toDateString(),
                $today->subMonth()->endOfMonth()->toDateString(),
            ],
            '6m' => [$today->subMonths(5)->startOfMonth()->toDateString(), $today->toDateString()],
            '12m' => [$today->subMonths(11)->startOfMonth()->toDateString(), $today->toDateString()],
            default => [$today->subDays(29)->toDateString(), $today->toDateString()],
        };
    }

    /** Vidjetlar bir ustunda, ketma-ket joylashsin. */
    public function getColumns(): int|string|array
    {
        return 1;
    }
}
