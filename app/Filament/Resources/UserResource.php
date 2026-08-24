<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\ShopsRelationManager;
use App\Models\User;
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
                    Infolists\Components\TextEntry::make('telegram_username')
                        ->label('Telegram')
                        ->placeholder('ulanmagan')
                        ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : '@' . $state),
                    Infolists\Components\TextEntry::make('telegram_chat_id')
                        ->label('Telegram ID')
                        ->placeholder('—')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('locale')->label('Til')->placeholder('—'),
                ]),
            Infolists\Components\Section::make('Holat')
                ->columns(3)
                ->schema([
                    Infolists\Components\IconEntry::make('is_blocked')
                        ->label('Bloklangan')
                        ->boolean()
                        ->state(fn (User $record): bool => $record->isBlocked())
                        ->trueColor('danger')
                        ->falseColor('success'),
                    Infolists\Components\TextEntry::make('blocked_at')->label('Bloklangan sana')->dateTime()->placeholder('—'),
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
                    ->searchable(['telegram_username', 'telegram_chat_id'])
                    ->placeholder('ulanmagan')
                    ->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : '@' . $state)
                    // chat_id ni ko'rsatamiz: "bu Telegram kimga bog'langan?"
                    // savoliga javob shu raqam orqali topiladi.
                    ->description(fn (User $record): ?string => $record->telegram_chat_id === null
                        ? null
                        : 'ID: ' . $record->telegram_chat_id)
                    ->copyable()
                    ->copyableState(fn (User $record): ?string => $record->telegram_chat_id === null
                        ? null
                        : (string) $record->telegram_chat_id),
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
                Tables\Columns\IconColumn::make('is_blocked')
                    ->label('Bloklangan')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->state(fn (User $record): bool => $record->isBlocked()),
                Tables\Columns\TextColumn::make('access_until')
                    ->label('Kirish muddati')
                    ->badge()
                    ->dateTime('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (User $record): string => match ($record->accessStatus()) {
                        'paid' => 'success',
                        'trial' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(function (User $record): string {
                        $label = match ($record->accessStatus()) {
                            'paid' => 'To\'langan',
                            'trial' => 'Sinov',
                            default => 'Tugagan',
                        };

                        $date = $record->access_until?->format('d.m.Y') ?? '—';

                        return "{$label} · {$date}";
                    })
                    ->description(function (User $record): ?string {
                        $days = $record->accessDaysLeft();

                        if ($days === null || $days < 0) {
                            return null;
                        }

                        return "{$days} kun qoldi";
                    }),
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
                Tables\Filters\Filter::make('is_blocked')
                    ->label('Bloklangan')
                    ->query(fn (Builder $q) => $q->whereNotNull('blocked_at'))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('access_status')
                    ->label('Kirish holati')
                    ->options([
                        'paid' => 'To\'langan',
                        'trial' => 'Sinov muddatida',
                        'expired' => 'Muddati tugagan',
                    ])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'paid' => $q->where('access_source', 'paid')->where('access_until', '>', now()),
                        'trial' => $q->where('access_source', '!=', 'paid')->where('access_until', '>', now()),
                        'expired' => $q->where(fn (Builder $sub) => $sub
                            ->whereNull('access_until')
                            ->orWhere('access_until', '<=', now())),
                        default => $q,
                    }),
                Tables\Filters\Filter::make('access_ending_soon')
                    ->label('Muddati 7 kun ichida tugaydi')
                    ->query(fn (Builder $q) => $q
                        ->whereBetween('access_until', [now(), now()->addDays(7)]))
                    ->toggle(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('grantAccess')
                    ->label('Premium berish')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->button()
                    ->modalHeading('Premium berish')
                    ->modalSubmitActionLabel('Ochish')
                    ->modalWidth('lg')
                    ->fillForm(fn (User $record): array => [
                        'duration' => '1_month',
                        'access_until' => static::defaultGrantDate($record),
                    ])
                    ->form([
                        Forms\Components\Placeholder::make('hozirgi')
                            ->label('Hozirgi holat')
                            ->content(fn (User $record): string => static::accessSummary($record)),
                        Forms\Components\Radio::make('duration')
                            ->label('Qancha muddatga')
                            ->options(static::GRANT_DURATIONS)
                            ->required()
                            ->live()
                            ->helperText('Muddat bugundan yoki mavjud muddat oxiridan — qaysi biri kechroq bo\'lsa — hisoblanadi.'),
                        Forms\Components\DatePicker::make('access_until')
                            ->label('Shu sanagacha ochiq')
                            ->native(false)
                            ->minDate(now())
                            ->required(fn (Forms\Get $get): bool => $get('duration') === 'custom')
                            ->visible(fn (Forms\Get $get): bool => $get('duration') === 'custom'),
                    ])
                    ->action(function (User $record, array $data): void {
                        static::applyGrant($record, $data);

                        Notification::make()
                            ->title('Premium ochildi: ' . $record->access_until->format('d.m.Y') . ' gacha')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('unlinkTelegram')
                    ->label('Telegramni uzish')
                    ->icon('heroicon-o-link-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Telegram hisobini uzish')
                    ->modalDescription(fn (User $record): string => 'Ushbu foydalanuvchidan @'
                        . ($record->telegram_username ?? $record->telegram_chat_id)
                        . ' uziladi. Shundan so\'ng u bot orqali xabar olmaydi, lekin bu Telegram '
                        . 'hisobini boshqa foydalanuvchiga ulash mumkin bo\'ladi.')
                    ->modalSubmitActionLabel('Uzish')
                    ->visible(fn (User $record): bool => $record->telegram_chat_id !== null)
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'telegram_chat_id' => null,
                            'telegram_username' => null,
                        ])->save();

                        Notification::make()->title('Telegram uzildi')->success()->send();
                    }),
                Tables\Actions\Action::make('revokeAccess')
                    ->label('Yopish')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->button()
                    ->outlined()
                    ->requiresConfirmation()
                    ->modalHeading('Pullik bo\'limlarni yopish')
                    ->modalDescription('Statistika, Buyurtmalar, Xodimlar va ikkinchi biznes darhol yopiladi. Bepul bo\'limlar ochiq qoladi.')
                    ->visible(fn (User $record): bool => $record->hasFullAccess())
                    ->action(function (User $record): void {
                        $record->forceFill(['access_until' => now()->subSecond()])->save();

                        Notification::make()->title('Pullik bo\'limlar yopildi')->success()->send();
                    }),
                Tables\Actions\Action::make('block')
                    ->label('Bloklash')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Foydalanuvchini bloklash')
                    ->modalDescription('Bloklangan foydalanuvchi ilovaga kira olmaydi va joriy sessiyalari tugatiladi.')
                    ->visible(fn (User $record): bool => ! $record->isBlocked())
                    ->action(function (User $record): void {
                        $record->forceFill(['blocked_at' => now()])->save();
                        $record->tokens()->delete();

                        Notification::make()->title('Foydalanuvchi bloklandi')->success()->send();
                    }),
                Tables\Actions\Action::make('unblock')
                    ->label('Blokdan chiqarish')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isBlocked())
                    ->action(function (User $record): void {
                        $record->forceFill(['blocked_at' => null])->save();

                        Notification::make()->title('Foydalanuvchi blokdan chiqarildi')->success()->send();
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

    /** Admin tanlay oladigan muddatlar. */
    private const GRANT_DURATIONS = [
        '1_month' => '1 oy',
        '3_months' => '3 oy',
        '6_months' => '6 oy',
        '1_year' => '1 yil',
        'custom' => 'Boshqa sana',
    ];

    /** Oynada ko'rsatiladigan hozirgi holat. */
    private static function accessSummary(User $user): string
    {
        $label = match ($user->accessStatus()) {
            'paid' => 'Premium',
            'trial' => 'Sinov muddatida',
            default => 'Muddati tugagan',
        };

        if ($user->access_until === null) {
            return $label;
        }

        $days = $user->accessDaysLeft();
        $suffix = $days !== null && $days >= 0
            ? " — {$days} kun qoldi"
            : ' — muddati o\'tgan';

        return $label . ' (' . $user->access_until->format('d.m.Y') . ')' . $suffix;
    }

    /** "Boshqa sana" tanlanganda taklif qilinadigan sana. */
    private static function defaultGrantDate(User $user): string
    {
        return static::grantStart($user)->copy()->addMonth()->toDateString();
    }

    /**
     * Sanoq boshlanadigan nuqta.
     *
     * Muddati tugamagan odamga qo'shimcha oy berilsa, qolgan kunlari
     * yo'qolib ketmasligi kerak — shuning uchun kechrog'i olinadi.
     */
    private static function grantStart(User $user): \Illuminate\Support\Carbon
    {
        return $user->access_until !== null && $user->access_until->isFuture()
            ? $user->access_until
            : now();
    }

    /**
     * Tanlangan muddatni qo'llaydi.
     *
     * `access_source` doim `paid` bo'ladi: admin qo'lda ochgan ekan, bu
     * endi sinov muddati emas.
     */
    private static function applyGrant(User $user, array $data): void
    {
        $until = match ($data['duration'] ?? null) {
            '1_month' => static::grantStart($user)->copy()->addMonth(),
            '3_months' => static::grantStart($user)->copy()->addMonths(3),
            '6_months' => static::grantStart($user)->copy()->addMonths(6),
            '1_year' => static::grantStart($user)->copy()->addYear(),
            'custom' => \Illuminate\Support\Carbon::parse($data['access_until'])->endOfDay(),
            default => static::grantStart($user)->copy()->addMonth(),
        };

        $user->forceFill([
            'access_until' => $until,
            'access_source' => 'paid',
        ])->save();
    }
}
