<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Enums\CashTransactionSource;
use App\Enums\CashTransactionType;
use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Models\CashTransaction;
use App\Models\ExpenseCategory;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Kassa: kirim/chiqim yozuvlari va ular qayerdan kelgani.
 *
 * Faqat qo'lda kiritilganlar tahrirlanadi — avtomatik yozuvlar (ishlab
 * chiqarish, qaytish, do'kon to'lovi) manbasidan observer orqali yangilanadi.
 */
class CashTransactionsRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'cashTransactions';

    protected static ?string $title = 'Kassa';

    protected static ?string $modelLabel = 'kassa yozuvi';

    protected static ?string $icon = 'heroicon-o-banknotes';

    private const SOURCES = [
        'manual' => 'Qo\'lda',
        'production' => 'Ishlab chiqarish',
        'return' => 'Qaytish',
        'outlet' => 'Do\'kon to\'lovi',
        'outlet_credit' => 'Do\'konga nasiya',
        'outlet_return' => 'Do\'kondan qaytish',
    ];

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->cashTransactions()->count();
    }

    /**
     * Kategoriya tanlovi: tizimdagilar + do'konning o'zi qo'shganlari.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(string $shopId, string $type): array
    {
        $builtIn = $type === CashTransactionType::Income->value
            ? collect(array_keys(config('expense_categories.built_in_income', [])))
                ->mapWithKeys(fn ($k) => [$k => __("cash.income_categories.{$k}", [], 'uz')])
            : collect(array_keys(config('expense_categories.built_in', [])))
                ->mapWithKeys(fn ($k) => [$k => __("expense.categories.{$k}", [], 'uz')]);

        $custom = ExpenseCategory::query()
            ->where('shop_id', $shopId)
            ->where('type', $type)
            ->pluck('name', 'id');

        return $builtIn->union($custom)->all();
    }

    public function form(Form $form): Form
    {
        $shopId = $this->getOwnerRecord()->getKey();

        return $form->schema([
            Forms\Components\Radio::make('type')
                ->label('Turi')
                ->options(['income' => 'Kirim', 'expense' => 'Chiqim'])
                ->default('income')
                ->inline()
                ->live()
                ->required(),
            Forms\Components\DatePicker::make('date')
                ->label('Sana')
                ->default(now())
                ->required(),
            Forms\Components\TextInput::make('amount')
                ->label('Summa')
                ->numeric()
                ->minValue(0.01)
                ->required(),
            Forms\Components\Select::make('category')
                ->label('Kategoriya')
                ->options(fn (Forms\Get $get) => static::categoryOptions($shopId, $get('type') ?? 'income'))
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Izoh')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();
        $money = fn ($state) => ShopInsights::money($state, $shop);
        $manual = fn (CashTransaction $r) => $r->isEditable();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->description(fn () => $this->totalsLine())
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Sana')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Turi')
                    ->badge()
                    ->formatStateUsing(fn (CashTransactionType $state) => $state === CashTransactionType::Income ? 'Kirim' : 'Chiqim')
                    ->color(fn (CashTransactionType $state) => $state === CashTransactionType::Income ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Manba')
                    ->formatStateUsing(fn (CashTransactionSource $state) => self::SOURCES[$state->value] ?? $state->value),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Summa')
                    ->formatStateUsing($money)
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoriya')
                    ->formatStateUsing(fn ($state, CashTransaction $r) => static::categoryOptions($shop->getKey(), $r->type->value)[$state] ?? $state)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Izoh')
                    ->placeholder('—')
                    ->limit(40),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Kim')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Turi')
                    ->options(['income' => 'Kirim', 'expense' => 'Chiqim']),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Manba')
                    ->options(self::SOURCES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Qo\'lda yozuv qo\'shish')
                    ->mutateFormDataUsing(fn (array $data) => $data + [
                        'source' => CashTransactionSource::Manual->value,
                        'created_by' => auth()->id(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible($manual),
                Tables\Actions\DeleteAction::make()->visible($manual),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Avtomatik yozuvlar manbasi bilan bog'liq — faqat qo'lda kiritilganlar o'chadi.
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(fn ($records) => $records->filter->isEditable()->each->delete()),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->defaultPaginationPageOption(25);
    }

    private function totalsLine(): string
    {
        $shop = $this->getOwnerRecord();
        $sum = fn (CashTransactionType $t) => (float) CashTransaction::query()
            ->where('shop_id', $shop->getKey())
            ->where('type', $t->value)
            ->sum('amount');

        $income = $sum(CashTransactionType::Income);
        $expense = $sum(CashTransactionType::Expense);

        return 'Kirim: ' . ShopInsights::money($income, $shop)
            . ' · Chiqim: ' . ShopInsights::money($expense, $shop)
            . ' · Qoldiq: ' . ShopInsights::money($income - $expense, $shop)
            . '. Avtomatik yozuvlar (ishlab chiqarish, qaytish, do\'kon) manbasini tahrirlaganda o\'zi yangilanadi.';
    }
}
