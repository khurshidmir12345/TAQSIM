<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IngredientResource\Pages;
use App\Models\Ingredient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IngredientResource extends Resource
{
    protected static ?string $model = Ingredient::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Xom ashyolar';

    protected static ?string $modelLabel = 'Xom ashyo';

    protected static ?string $pluralModelLabel = 'Xom ashyolar';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('shop_id')
                ->label('Do\'kon')
                ->relationship('shop', 'name')
                ->searchable()
                ->disabled(),
            Forms\Components\TextInput::make('name')
                ->label('Nomi')
                ->required(),
            Forms\Components\TextInput::make('price_per_unit')
                ->label('Birlik narxi')
                ->numeric(),
            Forms\Components\Toggle::make('is_flour')
                ->label('Un (asosiy xom ashyo)'),
            Forms\Components\Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Do\'kon')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price_per_unit')
                    ->label('Narxi')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_flour')
                    ->label('Un')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->label('Do\'kon')
                    ->relationship('shop', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_flour')
                    ->label('Un'),
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
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngredients::route('/'),
        ];
    }
}
