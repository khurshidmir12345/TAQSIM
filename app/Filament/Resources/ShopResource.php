<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages;
use App\Filament\Resources\ShopResource\RelationManagers\UsersRelationManager;
use App\Models\Shop;
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
                ]),
            Infolists\Components\Section::make('Statistika')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('users_count')->label('Xodimlar')->state(fn (Shop $r) => $r->users()->count()),
                    Infolists\Components\TextEntry::make('bread_categories_count')->label('Mahsulotlar')->state(fn (Shop $r) => $r->breadCategories()->count()),
                    Infolists\Components\TextEntry::make('productions_count')->label('Ishlab chiqarishlar')->state(fn (Shop $r) => $r->productions()->count()),
                    Infolists\Components\TextEntry::make('total_bread')->label('Jami non (dona)')->state(fn (Shop $r) => number_format((int) $r->productions()->sum('bread_produced'))),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['users', 'productions']))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('businessType.name_uz')
                    ->label('Biznes turi')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—')
                    ->toggleable(),
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
