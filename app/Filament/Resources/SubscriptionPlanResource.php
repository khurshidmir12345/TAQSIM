<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Tariflar';

    protected static ?string $modelLabel = 'Tarif';

    protected static ?string $pluralModelLabel = 'Tariflar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Asosiy')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kod (kalit)')
                        ->helperText('Masalan: trial, light, standart, premium')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(32),
                    Forms\Components\TextInput::make('price_usd')
                        ->label('Narx (USD)')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->default(0)
                        ->required()
                        ->suffix('$'),
                    Forms\Components\Select::make('billing_period')
                        ->label('Davr')
                        ->helperText('Yillik tarif uchun "Yillik" tanlang va davomiylikni 365 qiling')
                        ->options([
                            'monthly' => 'Oylik',
                            'yearly' => 'Yillik',
                        ])
                        ->default('monthly')
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('duration_days', $state === 'yearly' ? 365 : 30);
                        })
                        ->required(),
                    Forms\Components\TextInput::make('duration_days')
                        ->label('Davomiyligi (kun)')
                        ->numeric()
                        ->minValue(1)
                        ->default(30)
                        ->required(),
                    Forms\Components\TextInput::make('color')
                        ->label('Rang (HEX)')
                        ->placeholder('#0B3C5D')
                        ->maxLength(16),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Tartib')
                        ->numeric()
                        ->default(0),
                ]),

            Forms\Components\Section::make('Limitlar')
                ->description('Bo\'sh qoldirilsa — cheksiz')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('max_shops')
                        ->label('Biznes hisoblar')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('Cheksiz'),
                    Forms\Components\TextInput::make('max_products')
                        ->label('Mahsulotlar')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('Cheksiz'),
                    Forms\Components\TextInput::make('max_employees')
                        ->label('Xodimlar')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('Cheksiz'),
                ]),

            Forms\Components\Section::make('Holat')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Faol')
                        ->default(true),
                    Forms\Components\Toggle::make('is_popular')
                        ->label('Mashhur (tavsiya etilgan)')
                        ->default(false),
                    Forms\Components\Toggle::make('is_trial')
                        ->label('Bepul trial tarifi')
                        ->helperText('Yangi userlarga avtomat beriladi')
                        ->reactive()
                        ->default(false),
                    Forms\Components\TextInput::make('trial_days')
                        ->label('Trial kunlari')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('is_trial')),
                ]),

            Forms\Components\Section::make('Nomlar (tillar bo\'yicha)')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('name_uz')->label('O\'zbekcha')->required(),
                    Forms\Components\TextInput::make('name_uz_cyrl')->label('Ўзбекча (krill)'),
                    Forms\Components\TextInput::make('name_ru')->label('Ruscha'),
                    Forms\Components\TextInput::make('name_kk')->label('Qoraqalpoqcha'),
                    Forms\Components\TextInput::make('name_ky')->label('Qirg\'izcha'),
                    Forms\Components\TextInput::make('name_tr')->label('Turkcha'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_uz')
                    ->label('Tarif')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('price_usd')
                    ->label('Narx')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_period')
                    ->label('Davr')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'yearly' ? 'Yillik' : 'Oylik')
                    ->color(fn ($state) => $state === 'yearly' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('max_shops')
                    ->label('Biznes')
                    ->formatStateUsing(fn ($state) => $state ?? '∞')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('max_products')
                    ->label('Mahsulot')
                    ->formatStateUsing(fn ($state) => $state ?? '∞')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('max_employees')
                    ->label('Xodim')
                    ->formatStateUsing(fn ($state) => $state ?? '∞')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_trial')
                    ->label('Trial')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_popular')
                    ->label('Mashhur')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Faol holati'),
                Tables\Filters\TernaryFilter::make('is_trial')->label('Trial'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
