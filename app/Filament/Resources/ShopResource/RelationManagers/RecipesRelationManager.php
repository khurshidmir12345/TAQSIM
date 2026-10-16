<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Models\BreadCategory;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/** Retseptlar: 1 partiyaga qaysi xom ashyodan qancha ketadi va qancha turadi. */
class RecipesRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'recipes';

    protected static ?string $title = 'Retseptlar';

    protected static ?string $modelLabel = 'retsept';

    protected static ?string $icon = 'heroicon-o-beaker';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->recipes()->count();
    }

    public function form(Form $form): Form
    {
        $shopId = $this->getOwnerRecord()->getKey();

        return $form->schema([
            Forms\Components\Select::make('bread_category_id')
                ->label('Mahsulot')
                ->options(fn () => BreadCategory::query()->where('shop_id', $shopId)->orderBy('name')->pluck('name', 'id'))
                ->required()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                    if (blank($get('name')) && $state) {
                        $set('name', BreadCategory::query()->whereKey($state)->value('name'));
                    }
                })
                // Har bir mahsulotga bitta retsept (DB'da unique indeks bor).
                ->unique(
                    table: 'recipes',
                    column: 'bread_category_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('shop_id', $shopId)->whereNull('deleted_at'),
                )
                ->validationMessages(['unique' => 'Bu mahsulotning retsepti allaqachon bor — o\'shani tahrirlang.']),
            Forms\Components\TextInput::make('name')
                ->label('Retsept nomi')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('measurement_unit_id')
                ->label('Partiya birligi')
                ->options(fn () => MeasurementUnit::query()->batchAvailableFor($shopId)->active()
                    ->orderBy('sort_order')->pluck('name_uz', 'id'))
                ->default(fn () => MeasurementUnit::query()->global()->batch()->where('code', 'qop')->value('id'))
                ->required(),
            Forms\Components\TextInput::make('output_quantity')
                ->label('1 partiyadan chiqadi (dona)')
                ->integer()
                ->minValue(1)
                ->required(),
            Forms\Components\Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
            Forms\Components\Repeater::make('recipeIngredients')
                ->label('Tarkibi (1 partiya uchun)')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('ingredient_id')
                        ->label('Xom ashyo')
                        ->options(fn () => \App\Models\Ingredient::query()->where('shop_id', $shopId)
                            ->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->distinct()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    Forms\Components\TextInput::make('quantity')
                        ->label('Miqdori')
                        ->numeric()
                        ->minValue(0.001)
                        ->required(),
                ])
                ->columns(2)
                ->addActionLabel('Xom ashyo qo\'shish')
                ->defaultItems(1)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'breadCategory' => fn ($q) => $q->withTrashed(),
                'breadCategory.measurementUnit',
                'measurementUnit',
                'recipeIngredients.ingredient' => fn ($q) => $q->withTrashed(),
                'recipeIngredients.ingredient.measurementUnit',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('breadCategory.name')
                    ->label('Mahsulot')
                    ->description(fn (Recipe $r) => $r->name !== $r->breadCategory?->name ? $r->name : null)
                    ->weight('medium')
                    ->searchable(),
                Tables\Columns\TextColumn::make('output_quantity')
                    ->label('1 partiyadan')
                    ->formatStateUsing(fn ($state, Recipe $r) => '1 ' . ($r->measurementUnit?->name_uz ?? 'partiya')
                        . ' → ' . ShopInsights::number($state) . ' ' . ($r->breadCategory?->measurementUnit?->name_uz ?? 'dona')),
                Tables\Columns\TextColumn::make('composition')
                    ->label('Tarkibi (1 partiya uchun)')
                    ->state(fn (Recipe $r) => $r->recipeIngredients
                        ->sortByDesc(fn (RecipeIngredient $ri) => (float) $ri->quantity * (float) ($ri->ingredient?->price_per_unit ?? 0))
                        ->map(fn (RecipeIngredient $ri) => static::line($ri, $shop))
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('Xom ashyo yo\'q')
                    ->wrap(),
                Tables\Columns\TextColumn::make('batch_cost')
                    ->label('1 partiya xarajati')
                    ->state(fn (Recipe $r) => ShopInsights::batchCost($r))
                    ->formatStateUsing(fn ($state) => ShopInsights::money($state, $shop))
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('1 dona tannarx')
                    ->state(fn (Recipe $r) => ShopInsights::unitCost($r))
                    ->formatStateUsing(fn ($state) => ShopInsights::money($state, $shop))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('breadCategory.selling_price')
                    ->label('Sotuv narxi')
                    ->formatStateUsing(fn ($state) => ShopInsights::money($state, $shop))
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth('3xl')
                    ->mutateFormDataUsing(function (array $data) use ($shop) {
                        static::purgeTrashed($shop->getKey(), $data['bread_category_id']);

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('3xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions(static::bulkActions())
            ->paginated(false);
    }

    /**
     * Shu mahsulotning avval o'chirilgan retseptini butunlay tozalaydi —
     * (shop_id, bread_category_id) unique indeksi yangisini yaratishga
     * to'sqinlik qilmasin. API'dagi `purgeTrashedRecipesFor` bilan bir xil.
     */
    private static function purgeTrashed(string $shopId, string $breadCategoryId): void
    {
        Recipe::onlyTrashed()
            ->where('shop_id', $shopId)
            ->where('bread_category_id', $breadCategoryId)
            ->get()
            ->each(function (Recipe $old) {
                $old->recipeIngredients()->delete();
                $old->forceDelete();
            });
    }

    /** "Un — 50 kg × 5 660 = 283 000" */
    private static function line(RecipeIngredient $ri, Model $shop): string
    {
        $ingredient = $ri->ingredient;
        $unit = $ingredient?->measurementUnit?->code ?? $ingredient?->unit?->value ?? '';
        $price = (float) ($ingredient?->price_per_unit ?? 0);
        $qty = rtrim(rtrim(number_format((float) $ri->quantity, 3, '.', ' '), '0'), '.');
        $flour = $ingredient?->is_flour ? ' [un]' : '';
        $deleted = $ingredient?->deleted_at ? ' (o\'chirilgan)' : '';

        return ($ingredient?->name ?? '?') . $flour . $deleted
            . " — {$qty} {$unit} × " . ShopInsights::number($price)
            . ' = ' . ShopInsights::money((float) $ri->quantity * $price, $shop);
    }
}
