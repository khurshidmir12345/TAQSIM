<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Operatsiyalar';

    protected static ?string $navigationLabel = 'Xarajatlar';

    protected static ?string $modelLabel = 'Xarajat';

    protected static ?string $pluralModelLabel = 'Xarajatlar';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('shop_id')
                ->label('Do\'kon')
                ->relationship('shop', 'name')
                ->searchable()
                ->disabled(),
            Forms\Components\TextInput::make('category')
                ->label('Kategoriya'),
            Forms\Components\TextInput::make('amount')
                ->label('Summa')
                ->numeric(),
            Forms\Components\DatePicker::make('date')
                ->label('Sana'),
            Forms\Components\Textarea::make('description')
                ->label('Tavsif')
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
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoriya')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->numeric()
                    ->color('warning')
                    ->weight('medium')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')),
                Tables\Columns\TextColumn::make('description')
                    ->label('Tavsif')
                    ->limit(40)
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
            'index' => Pages\ListExpenses::route('/'),
        ];
    }
}
