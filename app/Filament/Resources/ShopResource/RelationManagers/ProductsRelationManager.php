<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Models\BreadCategory;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Do'kon mahsulotlari: narx, retsept bo'yicha tannarx va qancha ishlab chiqarilgani. */
class ProductsRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'breadCategories';

    protected static ?string $title = 'Mahsulotlar';

    protected static ?string $modelLabel = 'mahsulot';

    protected static ?string $icon = 'heroicon-o-cube';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->breadCategories()->count();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nomi')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('selling_price')
                ->label('Sotuv narxi')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Select::make('measurement_unit_id')
                ->label('Birlik')
                ->options(fn () => MeasurementUnit::query()->global()->ingredient()->active()
                    ->orderBy('sort_order')->pluck('name_uz', 'id'))
                ->default(fn () => static::defaultUnitId())
                ->placeholder('Dona'),
            Forms\Components\TextInput::make('sort_order')
                ->label('Tartib')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with([
                    'measurementUnit',
                    'recipes.measurementUnit',
                    'recipes.recipeIngredients.ingredient' => fn ($q) => $q->withTrashed(),
                ])
                ->withCount('productions')
                ->withSum('productions', 'bread_produced')
                ->withMax('productions', 'date'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Mahsulot')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Sotuv narxi')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ShopInsights::money($state, $shop)),
                Tables\Columns\TextColumn::make('output')
                    ->label('1 partiyadan')
                    ->state(fn (BreadCategory $r) => ($recipe = static::recipe($r))
                        ? '1 ' . ($recipe->measurementUnit?->name_uz ?? 'partiya') . ' → '
                            . ShopInsights::number($recipe->output_quantity) . ' ' . ($r->measurementUnit?->name_uz ?? 'dona')
                        : null)
                    ->placeholder('Retsept yo\'q'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Tannarx (1 dona)')
                    ->state(fn (BreadCategory $r) => static::unitCost($r))
                    ->formatStateUsing(fn ($state) => ShopInsights::money($state, $shop))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('unit_profit')
                    ->label('Foyda (1 dona)')
                    ->state(fn (BreadCategory $r) => ($c = static::unitCost($r)) === null
                        ? null
                        : (float) $r->selling_price - $c)
                    ->formatStateUsing(function ($state, BreadCategory $r) use ($shop) {
                        $price = (float) $r->selling_price;
                        $margin = $price > 0 ? ' (' . round($state / $price * 100, 1) . '%)' : '';

                        return ShopInsights::money($state, $shop) . $margin;
                    })
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('productions_sum_bread_produced')
                    ->label('Jami ishlab chiqarilgan')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ShopInsights::number($state) . ' dona')
                    ->placeholder('0'),
                Tables\Columns\TextColumn::make('productions_count')
                    ->label('Yozuvlar')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productions_max_date')
                    ->label('Oxirgi')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => array_merge($data, [
                        'currency_id' => $this->shopCurrencyId(),
                        'measurement_unit_id' => $data['measurement_unit_id'] ?? static::defaultUnitId(),
                    ])),
            ])
            ->actions(static::rowActions())
            ->bulkActions(static::bulkActions())
            ->defaultSort('productions_sum_bread_produced', 'desc')
            ->paginated(false);
    }

    /** Birlik tanlanmasa — "dona", API bilan bir xil. */
    private static function defaultUnitId(): ?string
    {
        return MeasurementUnit::query()->global()->ingredient()->where('code', 'ta')->value('id');
    }

    private static function recipe(BreadCategory $product): ?Recipe
    {
        return $product->recipes->first();
    }

    private static function unitCost(BreadCategory $product): ?float
    {
        $recipe = static::recipe($product);

        return $recipe ? ShopInsights::unitCost($recipe) : null;
    }
}
