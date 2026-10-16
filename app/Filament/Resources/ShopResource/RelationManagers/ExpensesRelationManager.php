<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Do'kon chiqimlari (ijara, gaz, ish haqi…). */
class ExpensesRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Chiqimlar';

    protected static ?string $modelLabel = 'chiqim';

    protected static ?string $icon = 'heroicon-o-receipt-percent';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->expenses()->count();
    }

    public function form(Form $form): Form
    {
        $shopId = $this->getOwnerRecord()->getKey();

        return $form->schema([
            Forms\Components\DatePicker::make('date')
                ->label('Sana')
                ->default(now())
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('Summa')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Select::make('category')
                ->label('Kategoriya')
                ->options(fn () => CashTransactionsRelationManager::categoryOptions($shopId, 'expense'))
                ->required(),
            Forms\Components\TextInput::make('description')
                ->label('Izoh')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();
        $money = fn ($state) => ShopInsights::money($state, $shop);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->columns([
                Tables\Columns\TextColumn::make('date')->label('Sana')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoriya')
                    ->formatStateUsing(fn ($state) => CashTransactionsRelationManager::categoryOptions($shop->getKey(), 'expense')[$state] ?? $state),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->formatStateUsing($money)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')->formatStateUsing($money)),
                Tables\Columns\TextColumn::make('description')->label('Izoh')->placeholder('—')->limit(40),
                Tables\Columns\TextColumn::make('creator.name')->label('Kim')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $data + ['created_by' => auth()->id()]),
            ])
            ->actions(static::rowActions())
            ->bulkActions(static::bulkActions())
            ->defaultSort('date', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
