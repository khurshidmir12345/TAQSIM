<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecipeResource\Pages;
use App\Models\Recipe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Retseptlar';

    protected static ?string $modelLabel = 'Retsept';

    protected static ?string $pluralModelLabel = 'Retseptlar';

    protected static ?int $navigationSort = 3;

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
            Forms\Components\TextInput::make('output_quantity')
                ->label('Chiqish miqdori')
                ->numeric(),
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
                Tables\Columns\TextColumn::make('breadCategory.name')
                    ->label('Mahsulot')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('output_quantity')
                    ->label('Chiqish (dona)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipe_ingredients_count')
                    ->label('Xom ashyolar')
                    ->counts('recipeIngredients')
                    ->badge()
                    ->color('info'),
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
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Faol holati'),
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
            'index' => Pages\ListRecipes::route('/'),
        ];
    }
}
