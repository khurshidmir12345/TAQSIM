<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BreadReturnResource\Pages;
use App\Models\BreadReturn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BreadReturnResource extends Resource
{
    protected static ?string $model = BreadReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Operatsiyalar';

    protected static ?string $navigationLabel = 'Qaytarilgan non';

    protected static ?string $modelLabel = 'Qaytarish';

    protected static ?string $pluralModelLabel = 'Qaytarilgan non';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('shop_id')
                ->label('Do\'kon')
                ->relationship('shop', 'name')
                ->searchable()
                ->disabled(),
            Forms\Components\TextInput::make('quantity')
                ->label('Soni (dona)')
                ->numeric(),
            Forms\Components\TextInput::make('price_per_unit')
                ->label('Dona narxi')
                ->numeric(),
            Forms\Components\TextInput::make('total_amount')
                ->label('Umumiy summa')
                ->numeric(),
            Forms\Components\DatePicker::make('date')
                ->label('Sana'),
            Forms\Components\Textarea::make('reason')
                ->label('Sabab')
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
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Soni')
                    ->numeric()
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Summa')
                    ->numeric()
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Sabab')
                    ->limit(30)
                    ->placeholder('—')
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
            'index' => Pages\ListBreadReturns::route('/'),
        ];
    }
}
