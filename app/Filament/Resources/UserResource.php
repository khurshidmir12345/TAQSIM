<?php

namespace App\Filament\Resources;

use App\Enums\WalletTransactionType;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\ShopsRelationManager;
use App\Models\User;
use App\Services\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Boshqaruv';

    protected static ?string $navigationLabel = 'Foydalanuvchilar';

    protected static ?string $modelLabel = 'Foydalanuvchi';

    protected static ?string $pluralModelLabel = 'Foydalanuvchilar';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Asosiy ma\'lumotlar')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ism')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(32),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\Select::make('locale')
                        ->label('Til')
                        ->options([
                            'uz' => 'O\'zbek',
                            'uz_CYRL' => 'Ўзбек (krill)',
                            'ru' => 'Rus',
                            'kk' => 'Qoraqalpoq',
                            'ky' => 'Qirg\'iz',
                            'tr' => 'Turk',
                        ]),
                ]),

            Forms\Components\Section::make('Telegram va integratsiyalar')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('telegram_username')
                        ->label('Telegram username')
                        ->prefix('@'),
                    Forms\Components\TextInput::make('telegram_chat_id')
                        ->label('Telegram chat ID')
                        ->numeric(),
                    Forms\Components\TextInput::make('avatar_url')
                        ->label('Avatar URL')
                        ->url()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Hisob va xavfsizlik')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('balance')
                        ->label('Balans')
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('password')
                        ->label('Parol (yangilash)')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->helperText('Bo\'sh qoldirsangiz, parol o\'zgarmaydi.'),
                    Forms\Components\Toggle::make('is_accepted_policy')
                        ->label('Shartlarni qabul qilgan'),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Profil')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Ism')->placeholder('—'),
                    Infolists\Components\TextEntry::make('phone')->label('Telefon')->placeholder('—')->copyable(),
                    Infolists\Components\TextEntry::make('email')->label('Email')->placeholder('—')->copyable(),
                    Infolists\Components\TextEntry::make('telegram_username')->label('Telegram')->prefix('@')->placeholder('—'),
                    Infolists\Components\TextEntry::make('locale')->label('Til')->placeholder('—'),
                    Infolists\Components\TextEntry::make('balance')->label('Balans')->numeric(),
                ]),
            Infolists\Components\Section::make('Holat')
                ->columns(3)
                ->schema([
                    Infolists\Components\IconEntry::make('is_accepted_policy')->label('Shartlar qabul qilingan')->boolean(),
                    Infolists\Components\TextEntry::make('phone_verified_at')->label('Telefon tasdiqlangan')->dateTime()->placeholder('Tasdiqlanmagan'),
                    Infolists\Components\TextEntry::make('email_verified_at')->label('Email tasdiqlangan')->dateTime()->placeholder('Tasdiqlanmagan'),
                    Infolists\Components\TextEntry::make('created_at')->label('Ro\'yxatdan o\'tgan')->dateTime(),
                    Infolists\Components\TextEntry::make('updated_at')->label('Yangilangan')->since(),
                    Infolists\Components\TextEntry::make('id')->label('ID')->copyable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('shops'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ism')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->prefix('@')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shops_count')
                    ->label('Do\'konlar')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\IconColumn::make('phone_verified_at')
                    ->label('Tasdiqlangan')
                    ->boolean()
                    ->state(fn (User $record): bool => $record->phone_verified_at !== null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ro\'yxatdan o\'tgan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_accepted_policy')
                    ->label('Shartlar qabul qilingan'),
                Tables\Filters\TernaryFilter::make('phone_verified')
                    ->label('Telefon tasdiqlangan')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('phone_verified_at'),
                        false: fn (Builder $q) => $q->whereNull('phone_verified_at'),
                    ),
                Tables\Filters\Filter::make('has_telegram')
                    ->label('Telegram ulangan')
                    ->query(fn (Builder $q) => $q->whereNotNull('telegram_chat_id'))
                    ->toggle(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('topUpBalance')
                    ->label('Balans to\'ldirish')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Summa (UZS)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Forms\Components\TextInput::make('description')
                            ->label('Izoh')
                            ->default('Admin tomonidan to\'ldirildi'),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(WalletService::class)->credit(
                            $record,
                            (float) $data['amount'],
                            WalletTransactionType::Topup,
                            $data['description'] ?? null,
                            null,
                            Auth::user(),
                        );

                        Notification::make()->title('Balans to\'ldirildi')->success()->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ShopsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
