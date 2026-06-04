<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemLinkResource\Pages;
use App\Models\SystemLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemLinkResource extends Resource
{
    protected static ?string $model = SystemLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Tizim';

    protected static ?string $navigationLabel = 'Tizim havolalari';

    protected static ?string $modelLabel = 'Havola';

    protected static ?string $pluralModelLabel = 'Tizim havolalari';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nomi')
                ->required(),
            Forms\Components\TextInput::make('type')
                ->label('Turi')
                ->helperText('Masalan: privacy, terms, support, telegram')
                ->required(),
            Forms\Components\TextInput::make('url')
                ->label('URL')
                ->url()
                ->required()
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->url(fn (SystemLink $record): string => $record->url)
                    ->openUrlInNewTab()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Faol holati'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSystemLinks::route('/'),
            'create' => Pages\CreateSystemLink::route('/create'),
            'edit' => Pages\EditSystemLink::route('/{record}/edit'),
        ];
    }
}
