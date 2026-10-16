<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Enums\ShopUserType;
use App\Filament\Resources\UserResource;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Xodimlar';

    protected static ?string $modelLabel = 'xodim';

    protected static ?string $icon = 'heroicon-o-user-group';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->users()->count();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private static function roleSelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('user_type')
            ->label('Roli')
            ->options(collect(ShopUserType::cases())->mapWithKeys(fn (ShopUserType $t) => [$t->value => $t->label()]))
            ->default(ShopUserType::Seller->value)
            ->required();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record) => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ism')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.user_type')
                    ->label('Roli')
                    ->badge()
                    // Pivot ustuni enum bo'lib keladi — `string` deb e'lon
                    // qilinganda TypeError berardi va sahifa umuman ochilmasdi.
                    ->color(fn (mixed $state): string => ShopUserType::resolve($state)?->badgeColor() ?? 'gray')
                    ->formatStateUsing(fn (mixed $state): string => ShopUserType::resolve($state)?->label() ?? '—'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('telegram_username')
                    ->label('Telegram')
                    ->prefix('@')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Xodim biriktirish')
                    ->modalHeading('Mavjud foydalanuvchini do\'konga biriktirish')
                    ->preloadRecordSelect(false)
                    ->recordSelectSearchColumns(['name', 'phone', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereNull('users.deleted_at'))
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()->label('Foydalanuvchi (ism, telefon yoki email)'),
                        static::roleSelect(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Rolni o\'zgartirish')
                    ->form([static::roleSelect()]),
                Tables\Actions\DetachAction::make()
                    ->label('Do\'kondan chiqarish')
                    ->modalDescription('Foydalanuvchi o\'chirilmaydi — faqat shu do\'kondan uziladi.'),
            ]);
    }
}
