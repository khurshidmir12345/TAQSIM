<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSettingResource\Pages;
use App\Models\AppSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppSettingResource extends Resource
{
    protected static ?string $model = AppSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Sozlamalar';

    protected static ?string $modelLabel = 'Sozlama';

    protected static ?string $pluralModelLabel = 'Sozlamalar';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Nomi')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('key')
                        ->label('Kalit')
                        ->required()
                        ->maxLength(64)
                        ->disabled(fn (?AppSetting $record) => $record !== null)
                        ->dehydrated(),
                    Forms\Components\TextInput::make('value')
                        ->label('Qiymat')
                        ->required(),
                    Forms\Components\TextInput::make('group')
                        ->label('Guruh')
                        ->default('general')
                        ->maxLength(32),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Nomi')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('key')->label('Kalit')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('value')->label('Qiymat')->weight('bold'),
                Tables\Columns\TextColumn::make('group')->label('Guruh')->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppSettings::route('/'),
            'create' => Pages\CreateAppSetting::route('/create'),
            'edit' => Pages\EditAppSetting::route('/{record}/edit'),
        ];
    }
}
