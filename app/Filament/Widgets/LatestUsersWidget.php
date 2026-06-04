<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestUsersWidget extends BaseWidget
{
    protected static ?string $heading = 'So\'nggi foydalanuvchilar';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()->withCount('shops')->latest()
            )
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ism')
                    ->placeholder('—')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->prefix('@')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('shops_count')
                    ->label('Do\'konlar')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ro\'yxatdan o\'tgan')
                    ->since()
                    ->sortable(),
            ]);
    }
}
