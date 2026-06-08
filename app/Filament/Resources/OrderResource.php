<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\WalletTransactionType;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\WalletService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Buyurtmalar';

    protected static ?string $modelLabel = 'Buyurtma';

    protected static ?string $pluralModelLabel = 'Buyurtmalar';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Raqam')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Foydalanuvchi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'topup' ? 'To\'ldirish' : 'Obuna')
                    ->color(fn (string $state): string => $state === 'topup' ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('plan_code')->label('Tarif')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('amount_local')
                    ->label('Summa')
                    ->money('UZS', divideBy: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Turi')->options([
                    'subscription' => 'Obuna',
                    'topup' => 'To\'ldirish',
                ]),
                Tables\Filters\SelectFilter::make('status')->label('Holat')->options([
                    'pending' => 'Kutilmoqda',
                    'paid' => 'To\'langan',
                    'failed' => 'Xato',
                    'cancelled' => 'Bekor qilingan',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approveTopup')
                    ->label('Tasdiqlash (kreditlash)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->type === OrderType::Topup->value
                        && $record->status === OrderStatus::Pending->value)
                    ->action(function (Order $record): void {
                        app(WalletService::class)->credit(
                            $record->user,
                            (float) $record->amount_local,
                            WalletTransactionType::Topup,
                            'Balans to\'ldirish (admin tasdiqlovi)',
                            $record,
                            Auth::user(),
                        );

                        $record->update([
                            'status' => OrderStatus::Paid->value,
                            'paid_at' => now(),
                        ]);

                        Notification::make()->title('Balans to\'ldirildi')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
