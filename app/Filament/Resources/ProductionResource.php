<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionResource\Pages;
use App\Models\Production;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationGroup = 'Operatsiyalar';

    protected static ?string $navigationLabel = 'Ishlab chiqarish';

    protected static ?string $modelLabel = 'Ishlab chiqarish';

    protected static ?string $pluralModelLabel = 'Ishlab chiqarish';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('shop_id')
                ->label('Do\'kon')
                ->relationship('shop', 'name')
                ->searchable()
                ->disabled(),
            Forms\Components\TextInput::make('breadCategory.name')
                ->label('Mahsulot')
                ->disabled(),
            Forms\Components\DatePicker::make('date')
                ->label('Sana'),
            Forms\Components\TextInput::make('flour_used_kg')
                ->label('Sarflangan un (kg)')
                ->numeric(),
            Forms\Components\TextInput::make('bread_produced')
                ->label('Ishlab chiqarilgan (dona)')
                ->numeric(),
            Forms\Components\TextInput::make('ingredient_cost')
                ->label('Xom ashyo tannarxi')
                ->numeric(),
            Forms\Components\Textarea::make('notes')
                ->label('Izoh')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Sana')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Do\'kon')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breadCategory.name')
                    ->label('Mahsulot')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('bread_produced')
                    ->label('Non (dona)')
                    ->numeric()
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('flour_used_kg')
                    ->label('Un (kg)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('ingredient_cost')
                    ->label('Tannarx')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Kim qo\'shgan')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->label('Do\'kon')
                    ->relationship('shop', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dan'),
                        Forms\Components\DatePicker::make('until')->label('Gacha'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductions::route('/'),
        ];
    }
}
