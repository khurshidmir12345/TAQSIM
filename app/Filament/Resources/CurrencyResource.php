<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Valyutalar';

    protected static ?string $modelLabel = 'Valyuta';

    protected static ?string $pluralModelLabel = 'Valyutalar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Kod')
                ->required()
                ->maxLength(8),
            Forms\Components\TextInput::make('name')
                ->label('Nomi')
                ->required(),
            Forms\Components\TextInput::make('symbol')
                ->label('Belgi')
                ->maxLength(8),
            Forms\Components\TextInput::make('sort_order')
                ->label('Tartib')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Belgi'),
                Tables\Columns\TextColumn::make('shops_count')
                    ->label('Do\'konlar')
                    ->counts('shops')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Tartib')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
