<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BotChatResource\Pages;
use App\Models\BotChat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BotChatResource extends Resource
{
    protected static ?string $model = BotChat::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Bot guruhlari';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('system_bot_id')
                ->relationship('bot', 'name')
                ->required()
                ->label('Bot'),
            Forms\Components\TextInput::make('chat_id')
                ->required()
                ->label('Guruh ID')
                ->helperText('Bot guruhga qo\'shilganda avtomatik yoziladi.'),
            Forms\Components\TextInput::make('title')
                ->label('Guruh nomi')
                ->maxLength(255),
            Forms\Components\Select::make('purpose')
                ->label('Vazifasi')
                ->options([
                    BotChat::PURPOSE_NOTIFY => 'Notify — kod va bildirishnomalar',
                    BotChat::PURPOSE_SUPPORT => 'Support — foydalanuvchi savollari',
                ])
                ->helperText('Bo\'sh qoldirilsa guruh hech narsa uchun ishlatilmaydi.'),
            Forms\Components\Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bot.name')
                    ->label('Bot')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Guruh')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('chat_id')
                    ->label('Guruh ID')
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Vazifasi')
                    ->badge()
                    ->placeholder('belgilanmagan')
                    ->color(fn (?string $state): string => match ($state) {
                        BotChat::PURPOSE_SUPPORT => 'success',
                        BotChat::PURPOSE_NOTIFY => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Yangilangan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('purpose')
                    ->label('Vazifasi')
                    ->options([
                        BotChat::PURPOSE_NOTIFY => 'Notify',
                        BotChat::PURPOSE_SUPPORT => 'Support',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBotChats::route('/'),
            'create' => Pages\CreateBotChat::route('/create'),
            'edit' => Pages\EditBotChat::route('/{record}/edit'),
        ];
    }
}
