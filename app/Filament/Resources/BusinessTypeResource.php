<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessTypeResource\Pages;
use App\Models\BusinessType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BusinessTypeResource extends Resource
{
    protected static ?string $model = BusinessType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Biznes turlari';

    protected static ?string $modelLabel = 'Biznes turi';

    protected static ?string $pluralModelLabel = 'Biznes turlari';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Asosiy')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Kalit (key)')
                        ->required(),
                    Forms\Components\TextInput::make('icon')
                        ->label('Ikonka'),
                    Forms\Components\TextInput::make('color')
                        ->label('Rang'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Tartib')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Faol')
                        ->default(true),
                ]),
            Forms\Components\Section::make('Nomlar (tillar bo\'yicha)')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name_uz')->label('O\'zbekcha')->required(),
                    Forms\Components\TextInput::make('name_uz_cyrl')->label('Ўзбекча (krill)'),
                    Forms\Components\TextInput::make('name_ru')->label('Ruscha'),
                    Forms\Components\TextInput::make('name_kk')->label('Qoraqalpoqcha'),
                    Forms\Components\TextInput::make('name_ky')->label('Qirg\'izcha'),
                    Forms\Components\TextInput::make('name_tr')->label('Turkcha'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_uz')
                    ->label('Nomi (uz)')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('key')
                    ->label('Kalit')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shops_count')
                    ->label('Do\'konlar')
                    ->counts('shops')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Tartib')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Faol holati'),
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
            'index' => Pages\ListBusinessTypes::route('/'),
            'create' => Pages\CreateBusinessType::route('/create'),
            'edit' => Pages\EditBusinessType::route('/{record}/edit'),
        ];
    }
}
