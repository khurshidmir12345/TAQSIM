<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Obunalar';

    protected static ?string $modelLabel = 'Obuna';

    protected static ?string $pluralModelLabel = 'Obunalar';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('plan', 'user'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Foydalanuvchi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan.name_uz')
                    ->label('Tarif')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->state(fn (Subscription $record): string => $record->effectiveStatus()->value)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        'grace' => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\IconColumn::make('is_current')->label('Joriy')->boolean(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Tugaydi')
                    ->dateTime('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Boshlandi')->dateTime('d.m.Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_current')->label('Joriy obuna'),
            ])
            ->actions([
                Tables\Actions\Action::make('grant')
                    ->label('Tarif berish')
                    ->icon('heroicon-o-gift')
                    ->form([
                        Forms\Components\Select::make('plan_id')
                            ->label('Tarif')
                            ->options(fn () => SubscriptionPlan::query()
                                ->where('is_active', true)
                                ->where('is_trial', false)
                                ->pluck('name_uz', 'id'))
                            ->required(),
                    ])
                    ->action(function (Subscription $record, array $data): void {
                        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
                        app(SubscriptionService::class)->activatePlan($record->user, $plan);

                        Notification::make()->title('Tarif berildi')->success()->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Bekor qilish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => $record->is_current && $record->cancelled_at === null)
                    ->action(function (Subscription $record): void {
                        $record->update(['cancelled_at' => now(), 'is_current' => false]);

                        Notification::make()->title('Obuna bekor qilindi')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
