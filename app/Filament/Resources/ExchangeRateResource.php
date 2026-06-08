<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExchangeRateResource\Pages;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Valyuta kurslari';

    protected static ?string $modelLabel = 'Valyuta kursi';

    protected static ?string $pluralModelLabel = 'Valyuta kurslari';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('base_code')
                        ->label('Asosiy valyuta')
                        ->default('USD')
                        ->required()
                        ->maxLength(8),
                    Forms\Components\TextInput::make('quote_code')
                        ->label('Maqsad valyuta')
                        ->default('UZS')
                        ->required()
                        ->maxLength(8),
                    Forms\Components\TextInput::make('rate')
                        ->label('Kurs')
                        ->numeric()
                        ->step('0.0001')
                        ->required(),
                    Forms\Components\Select::make('source')
                        ->label('Manba')
                        ->options(['manual' => 'Qo\'lda', 'cbu' => 'CBU'])
                        ->default('manual'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Faol')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('base_code')->label('Asosiy')->badge(),
                Tables\Columns\TextColumn::make('quote_code')->label('Maqsad')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('rate')
                    ->label('Kurs')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')->label('Manba')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Faol')->boolean(),
                Tables\Columns\TextColumn::make('fetched_at')->label('Yangilangan')->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('syncCbu')
                    ->label('CBU\'dan yangilash')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (): void {
                        $rate = app(ExchangeRateService::class)->syncFromCbu();

                        if ($rate !== null) {
                            Notification::make()
                                ->title("Kurs yangilandi: 1 USD = {$rate} UZS")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('CBU\'dan kursni olishda xatolik')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('base_code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExchangeRates::route('/'),
            'create' => Pages\CreateExchangeRate::route('/create'),
            'edit' => Pages\EditExchangeRate::route('/{record}/edit'),
        ];
    }
}
