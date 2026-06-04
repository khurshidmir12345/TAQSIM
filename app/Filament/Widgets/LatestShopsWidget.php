<?php

namespace App\Filament\Widgets;

use App\Models\Shop;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestShopsWidget extends BaseWidget
{
    protected static ?string $heading = 'So\'nggi do\'konlar';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Shop::query()
                    ->withCount(['users', 'productions'])
                    ->latest()
            )
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Xodimlar')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('productions_count')
                    ->label('Ishlab chiqarish')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Qo\'shilgan')
                    ->since()
                    ->sortable(),
            ]);
    }
}
