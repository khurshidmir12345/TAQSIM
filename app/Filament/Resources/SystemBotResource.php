<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemBotResource\Pages;
use App\Models\SystemBot;
use App\Services\TelegramBotService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemBotResource extends Resource
{
    protected static ?string $model = SystemBot::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'System Bots';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('username')
                ->required()
                ->maxLength(255)
                ->prefix('@'),
            Forms\Components\Select::make('type')
                ->options([
                    'register' => 'Register',
                    'notify' => 'Notify',
                ])
                ->required(),
            Forms\Components\TextInput::make('token')
                ->required()
                ->maxLength(500)
                ->password()
                ->revealable(),
            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('username')
                    ->prefix('@'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'register' => 'success',
                        'notify' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('webhook_url')
                    ->limit(40)
                    ->placeholder('Not set'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('setWebhook')
                    ->label('Set Webhook')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Set Telegram Webhook')
                    ->modalDescription('This will set the webhook for this bot to the current server.')
                    ->action(function (SystemBot $record) {
                        $service = app(TelegramBotService::class);
                        $webhookUrl = config('app.url') . '/api/telegram/webhook/' . $record->token;

                        $result = $service->setWebhook($record->token, $webhookUrl);

                        if (isset($result['ok']) && $result['ok']) {
                            $record->update(['webhook_url' => $webhookUrl]);
                            Notification::make()
                                ->title('Webhook muvaffaqiyatli o\'rnatildi!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Webhook o\'rnatishda xatolik!')
                                ->body($result['description'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemBots::route('/'),
            'create' => Pages\CreateSystemBot::route('/create'),
            'edit' => Pages\EditSystemBot::route('/{record}/edit'),
        ];
    }
}
