<?php

namespace App\Filament\Resources;

use App\Enums\ShopUserType;
use App\Filament\Resources\ShopResource\Pages;
use App\Filament\Resources\ShopResource\RelationManagers;
use App\Filament\Resources\ShopResource\RelationManagers\UsersRelationManager;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Boshqaruv';

    protected static ?string $navigationLabel = 'Do\'konlar';

    protected static ?string $modelLabel = 'Do\'kon';

    protected static ?string $pluralModelLabel = 'Do\'konlar';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Do\'kon ma\'lumotlari')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nomi')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(32),
                    Forms\Components\Select::make('business_type_id')
                        ->label('Biznes turi')
                        ->relationship('businessType', 'name_uz')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('currency_id')
                        ->label('Valyuta')
                        ->relationship('currency', 'code')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('address')
                        ->label('Manzil')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('Tavsif')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Faol')
                        ->default(true),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Do\'kon')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nomi'),
                    Infolists\Components\TextEntry::make('phone')->label('Telefon')->placeholder('—')->copyable(),
                    Infolists\Components\TextEntry::make('businessType.name_uz')->label('Biznes turi')->placeholder('—'),
                    Infolists\Components\TextEntry::make('currency.code')->label('Valyuta')->placeholder('—'),
                    Infolists\Components\IconEntry::make('is_active')->label('Faol')->boolean(),
                    Infolists\Components\TextEntry::make('created_at')->label('Yaratilgan')->dateTime(),
                    Infolists\Components\TextEntry::make('address')->label('Manzil')->placeholder('—')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('description')->label('Tavsif')->placeholder('—')->columnSpanFull(),
                ]),
            Infolists\Components\Section::make('Egasi')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('owner_name')
                        ->label('Ism')
                        ->state(fn (Shop $r) => static::owner($r)?->name)
                        ->placeholder('Egasi yo\'q yoki o\'chirilgan')
                        ->weight('medium')
                        ->color('primary')
                        ->url(fn (Shop $r) => ($owner = static::owner($r))
                            ? UserResource::getUrl('view', ['record' => $owner])
                            : null),
                    Infolists\Components\TextEntry::make('owner_phone')
                        ->label('Telefon')
                        ->state(fn (Shop $r) => static::owner($r)?->phone)
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('owner_email')
                        ->label('Email')
                        ->state(fn (Shop $r) => static::owner($r)?->email)
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('owner_telegram')
                        ->label('Telegram')
                        ->state(fn (Shop $r) => ($u = static::owner($r)?->telegram_username) ? '@' . $u : null)
                        ->placeholder('ulanmagan'),
                    Infolists\Components\TextEntry::make('owner_access')
                        ->label('Kirish muddati')
                        ->badge()
                        ->state(fn (Shop $r) => static::ownerAccessLabel(static::owner($r)))
                        ->color(fn (Shop $r) => match (static::owner($r)?->accessStatus()) {
                            'paid' => 'success',
                            'trial' => 'warning',
                            default => 'danger',
                        }),
                    Infolists\Components\TextEntry::make('owner_created')
                        ->label('Ro\'yxatdan o\'tgan')
                        ->state(fn (Shop $r) => static::owner($r)?->created_at)
                        ->dateTime('d.m.Y H:i')
                        ->placeholder('—'),
                ]),
            Infolists\Components\Section::make('Faollik')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label('Holat')
                        ->badge()
                        ->state(fn (Shop $r) => ShopInsights::for($r)['status']['label'])
                        ->color(fn (Shop $r) => ShopInsights::for($r)['status']['color']),
                    Infolists\Components\TextEntry::make('first_date')
                        ->label('Birinchi ishlab chiqarish')
                        ->state(fn (Shop $r) => ShopInsights::for($r)['first_date'])
                        ->date('d.m.Y')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('last_date')
                        ->label('Oxirgi ishlab chiqarish')
                        ->state(fn (Shop $r) => ShopInsights::for($r)['last_date'])
                        ->date('d.m.Y')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('days')
                        ->label('Ishlagan kunlar')
                        ->state(fn (Shop $r) => ShopInsights::for($r)['days']),
                    static::countEntry('entries', 'Ishlab chiqarish yozuvlari'),
                    static::countEntry('products', 'Mahsulotlar'),
                    static::countEntry('recipes', 'Retseptlar'),
                    static::countEntry('ingredients', 'Xom ashyolar'),
                    static::countEntry('users', 'Xodimlar'),
                    static::countEntry('cash', 'Kassa yozuvlari'),
                    static::countEntry('orders', 'Zakazlar'),
                    static::countEntry('customers', 'Mijozlar'),
                    static::countEntry('outlets', 'Savdo nuqtalari'),
                    static::countEntry('returns', 'Qaytishlar'),
                    static::countEntry('expenses', 'Chiqimlar'),
                ]),
            Infolists\Components\Section::make('Hisobiy natija')
                ->description('Tushum = chiqqan mahsulot × hozirgi sotuv narxi. Qaytishlar va kassadagi haqiqiy pul hisobga olinmagan.')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('produced')
                        ->label('Jami ishlab chiqarilgan')
                        ->state(fn (Shop $r) => ShopInsights::number(ShopInsights::for($r)['produced']) . ' dona')
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('per_day')
                        ->label('O\'rtacha kunlik')
                        ->state(fn (Shop $r) => ShopInsights::number(ShopInsights::for($r)['per_day']) . ' dona'),
                    Infolists\Components\TextEntry::make('cost')
                        ->label('Xom ashyo xarajati')
                        ->state(fn (Shop $r) => ShopInsights::money(ShopInsights::for($r)['cost'], $r)),
                    Infolists\Components\TextEntry::make('revenue')
                        ->label('Hisobiy tushum')
                        ->state(fn (Shop $r) => ShopInsights::money(ShopInsights::for($r)['revenue'], $r)),
                    Infolists\Components\TextEntry::make('profit')
                        ->label('Hisobiy foyda')
                        ->weight('bold')
                        ->state(function (Shop $r): string {
                            $i = ShopInsights::for($r);

                            return ShopInsights::money($i['profit'], $r)
                                . ($i['margin'] !== null ? " ({$i['margin']}%)" : '');
                        })
                        ->color(fn (Shop $r) => ShopInsights::for($r)['profit'] < 0 ? 'danger' : 'success'),
                ]),
            Infolists\Components\Section::make('Sozlamadagi kamchiliklar')
                ->description('Foydalanuvchiga yordam berish uchun: bular hisob-kitobni noto\'g\'ri qiladi.')
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('warning')
                ->collapsible()
                ->visible(fn (Shop $r) => ShopInsights::for($r)['warnings'] !== [])
                ->schema([
                    Infolists\Components\TextEntry::make('warnings')
                        ->hiddenLabel()
                        ->state(fn (Shop $r) => ShopInsights::for($r)['warnings'])
                        ->listWithLineBreaks()
                        ->bulleted(),
                ]),
        ]);
    }

    private static function countEntry(string $key, string $label): Infolists\Components\TextEntry
    {
        return Infolists\Components\TextEntry::make($key)
            ->label($label)
            ->state(fn (Shop $r) => ShopInsights::number(ShopInsights::for($r)[$key]));
    }

    /** Egasi — bitta so'rov ichida bir marta yuklanadi. */
    private static function owner(Shop $shop): ?User
    {
        if (! $shop->relationLoaded('ownerUser')) {
            $shop->setRelation('ownerUser', $shop->owner());
        }

        return $shop->getRelation('ownerUser');
    }

    private static function ownerAccessLabel(?User $user): string
    {
        if ($user === null) {
            return '—';
        }

        $label = match ($user->accessStatus()) {
            'paid' => 'To\'langan',
            'trial' => 'Sinov',
            default => 'Tugagan',
        };

        return $label . ' · ' . ($user->access_until?->format('d.m.Y') ?? '—');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['users' => fn ($q) => $q->wherePivot('user_type', ShopUserType::Owner->value)])
                ->withCount(['users', 'productions', 'breadCategories', 'recipes'])
                ->withMax('productions', 'date'))
            ->recordUrl(fn (Shop $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('owner')
                    ->label('Egasi')
                    ->state(fn (Shop $r) => $r->users->first()?->name)
                    ->description(fn (Shop $r) => $r->users->first()?->phone ?? $r->users->first()?->email)
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('users', fn (Builder $u) => $u
                        ->where('user_shops.user_type', ShopUserType::Owner->value)
                        ->where(fn (Builder $w) => $w
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")))),
                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->state(fn (Shop $r) => ShopInsights::status((int) $r->recipes_count, $r->productions_max_date)['label'])
                    ->color(fn (Shop $r) => ShopInsights::status((int) $r->recipes_count, $r->productions_max_date)['color']),
                Tables\Columns\TextColumn::make('bread_categories_count')
                    ->label('Mahsulot')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('productions_max_date')
                    ->label('Oxirgi faollik')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessType.name_uz')
                    ->label('Biznes turi')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Xodimlar')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('productions_count')
                    ->label('Ishlab chiqarish')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency.code')
                    ->label('Valyuta')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaratilgan')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Faol holati'),
                Tables\Filters\SelectFilter::make('business_type_id')
                    ->label('Biznes turi')
                    ->relationship('businessType', 'name_uz')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
            RelationManagers\RecipesRelationManager::class,
            RelationManagers\IngredientsRelationManager::class,
            RelationManagers\ProductionsRelationManager::class,
            RelationManagers\CashTransactionsRelationManager::class,
            RelationManagers\ReturnsRelationManager::class,
            RelationManagers\ExpensesRelationManager::class,
            UsersRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShops::route('/'),
            'view' => Pages\ViewShop::route('/{record}'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
