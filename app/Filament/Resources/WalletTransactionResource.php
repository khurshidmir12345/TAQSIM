<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages;
use App\Models\WalletTransaction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Balans tarixi';

    protected static ?string $modelLabel = 'Tranzaksiya';

    protected static ?string $pluralModelLabel = 'Balans tarixi';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Foydalanuvchi')->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'topup' => 'To\'ldirish',
                        'subscription_charge' => 'Obuna to\'lovi',
                        'refund' => 'Qaytarish',
                        'adjustment' => 'Tuzatish',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->money('UZS', divideBy: 1)
                    ->color(fn (WalletTransaction $record): string => (float) $record->amount >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('balance_after')->label('Balans')->money('UZS', divideBy: 1),
                Tables\Columns\TextColumn::make('description')->label('Izoh')->limit(40)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Turi')->options([
                    'topup' => 'To\'ldirish',
                    'subscription_charge' => 'Obuna to\'lovi',
                    'refund' => 'Qaytarish',
                    'adjustment' => 'Tuzatish',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
