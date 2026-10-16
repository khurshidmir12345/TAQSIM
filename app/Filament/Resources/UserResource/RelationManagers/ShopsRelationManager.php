<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ShopUserType;
use App\Filament\Resources\ShopResource;
use App\Models\Shop;
use App\Services\Admin\ShopInsights;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Foydalanuvchining do'konlari — qatorni bossangiz do'konning to'liq sahifasi ochiladi. */
class ShopsRelationManager extends RelationManager
{
    protected static string $relationship = 'shops';

    protected static ?string $title = 'Do\'konlar';

    protected static ?string $icon = 'heroicon-o-building-storefront';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->shops()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['businessType', 'currency'])
                ->withCount(['breadCategories', 'recipes', 'productions'])
                ->withMax('productions', 'date'))
            ->recordUrl(fn (Shop $record) => ShopResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->weight('medium')
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.user_type')
                    ->label('Roli')
                    ->badge()
                    // Pivot ustuni enum bo'lib keladi — `string` deb e'lon
                    // qilinganda TypeError berardi va sahifa umuman ochilmasdi.
                    ->color(fn (mixed $state): string => ShopUserType::resolve($state)?->badgeColor() ?? 'gray')
                    ->formatStateUsing(fn (mixed $state): string => ShopUserType::resolve($state)?->label() ?? '—'),
                Tables\Columns\TextColumn::make('businessType.name_uz')
                    ->label('Biznes turi')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->state(fn (Shop $r) => static::status($r)['label'])
                    ->color(fn (Shop $r) => static::status($r)['color']),
                Tables\Columns\TextColumn::make('bread_categories_count')
                    ->label('Mahsulot')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('recipes_count')
                    ->label('Retsept')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('productions_count')
                    ->label('Ishlab chiqarish')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('productions_max_date')
                    ->label('Oxirgi faollik')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaratilgan')
                    ->date('d.m.Y'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Ochish')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (Shop $record) => ShopResource::getUrl('view', ['record' => $record])),
            ]);
    }

    /** Yuklangan `recipes_count` va `productions_max_date` dan — qo'shimcha so'rovsiz. */
    private static function status(Shop $shop): array
    {
        return ShopInsights::status((int) $shop->recipes_count, $shop->productions_max_date);
    }
}
