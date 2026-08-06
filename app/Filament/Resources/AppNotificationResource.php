<?php

namespace App\Filament\Resources;

use App\Enums\NotificationCategory;
use App\Filament\Resources\AppNotificationResource\Pages;
use App\Models\AppNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Yuborilgan bildirishnomalar ro'yxati.
 *
 * Faqat ko'rish uchun — yangi bildirishnoma "Bildirishnoma yuborish"
 * sahifasidan yuboriladi.
 */
class AppNotificationResource extends Resource
{
    protected static ?string $model = AppNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Yuborilgan bildirishnomalar';

    protected static ?int $navigationSort = 81;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Vaqti')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Kimga')
                    ->description(fn (AppNotification $r): string => $r->user?->phone ?? '')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Turi')
                    ->badge()
                    ->formatStateUsing(fn (NotificationCategory $state): string => match ($state) {
                        NotificationCategory::Admin => 'Admin',
                        NotificationCategory::DailyGreeting => 'Kunlik tilak',
                        NotificationCategory::OrderReminder => 'Zakaz',
                        NotificationCategory::EmployeeAdded => 'Xodim',
                        NotificationCategory::System => 'Tizim',
                    })
                    ->color(fn (NotificationCategory $state): string => match ($state) {
                        NotificationCategory::Admin => 'warning',
                        NotificationCategory::System => 'danger',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Sarlavha')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('body')
                    ->label('Matn')
                    ->limit(60)
                    ->tooltip(fn (AppNotification $r): string => $r->body)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('read_at')
                    ->label('O\'qilgan')
                    ->boolean()
                    ->getStateUsing(fn (AppNotification $r): bool => $r->isRead()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Turi')
                    ->options([
                        NotificationCategory::Admin->value => 'Admin',
                        NotificationCategory::DailyGreeting->value => 'Kunlik tilak',
                        NotificationCategory::OrderReminder->value => 'Zakaz',
                        NotificationCategory::EmployeeAdded->value => 'Xodim',
                        NotificationCategory::System->value => 'Tizim',
                    ]),

                Tables\Filters\TernaryFilter::make('read_at')
                    ->label('O\'qilgan')
                    ->nullable()
                    ->trueLabel('O\'qilgan')
                    ->falseLabel('O\'qilmagan')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('read_at'),
                        false: fn (Builder $q) => $q->whereNull('read_at'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Hali bildirishnoma yuborilmagan');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppNotifications::route('/'),
        ];
    }
}
