<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ShopUserType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShopsRelationManager extends RelationManager
{
    protected static string $relationship = 'shops';

    protected static ?string $title = 'Do\'konlar';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
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
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Ko\'rish')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => \App\Filament\Resources\ShopResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
