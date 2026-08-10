<?php

namespace App\Filament\Widgets\Statistics;

use App\Enums\ShopUserType;
use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Models\Shop;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ro'yxat — admin bu do'kon egalari bilan bog'lanib, nega ishlatmayotganini
 * so'rashi mumkin. Telefon raqami nusxalanadigan qilib qo'yilgan.
 */
class ConfiguredNotStartedTableWidget extends BaseWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->heading('Sozlagan, lekin ishlatmagan do\'konlar')
            ->description('Tanlangan davrda ochilganlar. Mahsulot / xom ashyo / retsept bor, ammo kirim, vozvrat, xarajat va zakaz yo\'q.')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Bunday do\'kon yo\'q')
            ->emptyStateDescription('Bu davrda ochilgan do\'konlarning hammasi ishga tushgan.')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Do\'kon')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Egasi')
                    ->state(fn (Shop $record): string => $this->owner($record)?->name ?: '—'),

                Tables\Columns\TextColumn::make('owner_phone')
                    ->label('Telefon')
                    ->state(fn (Shop $record): string => $this->owner($record)?->phone ?: '—')
                    ->copyable()
                    ->copyMessage('Nusxalandi'),

                Tables\Columns\TextColumn::make('bread_categories_count')
                    ->label('Mahsulot')
                    ->counts('breadCategories')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('ingredients_count')
                    ->label('Xom ashyo')
                    ->counts('ingredients')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('recipes_count')
                    ->label('Retsept')
                    ->counts('recipes')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ochilgan')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->description(fn (Shop $record): string => $record->created_at?->diffForHumans() ?? '—'),
            ])
            ->paginationPageOptions([10, 25, 50]);
    }

    /** @return Builder<Shop> */
    private function baseQuery(): Builder
    {
        return $this->statistics()
            ->configuredNotStartedQuery($this->from(), $this->to())
            ->with(['users' => fn ($q) => $q->wherePivot('user_type', ShopUserType::Owner->value)]);
    }

    private function owner(Shop $shop): ?User
    {
        return $shop->users->first();
    }
}
