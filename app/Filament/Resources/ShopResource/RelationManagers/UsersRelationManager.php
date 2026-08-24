<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Enums\ShopUserType;
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
                    // Pivot ustuni enum bo'lib keladi — `string` deb e'lon
                    // qilinganda TypeError berardi va sahifa umuman ochilmasdi.
                    ->color(fn (mixed $state): string => ShopUserType::resolve($state)?->badgeColor() ?? 'gray')
                    ->formatStateUsing(fn (mixed $state): string => ShopUserType::resolve($state)?->label() ?? '—'),
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
