<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Xodimlar';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ism')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.user_type')
                    ->label('Roli')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'owner' ? 'success' : 'info')
                    ->formatStateUsing(fn (string $state): string => $state === 'owner' ? 'Egasi' : 'Sotuvchi'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->prefix('@')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Ko\'rish')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => \App\Filament\Resources\UserResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
