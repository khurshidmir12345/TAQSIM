<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Xom ashyolar: narxi, birligi va qaysi retseptlarda ishlatilgani. */
class IngredientsRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'ingredients';

    protected static ?string $title = 'Xom ashyolar';

    protected static ?string $modelLabel = 'xom ashyo';

    protected static ?string $icon = 'heroicon-o-shopping-bag';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->ingredients()->count();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nomi')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('price_per_unit')
                ->label('Narxi (1 birlik)')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Select::make('measurement_unit_id')
                ->label('Birlik')
                ->options(fn () => MeasurementUnit::query()->global()->ingredient()->active()
                    ->orderBy('sort_order')->pluck('name_uz', 'id'))
                ->default(fn () => MeasurementUnit::query()->global()->ingredient()->where('code', 'kg')->value('id'))
                ->required(),
            Forms\Components\Toggle::make('is_flour')
                ->label('Bu un (sarflangan un hisobiga kiradi)'),
            Forms\Components\Toggle::make('is_active')
                ->label('Faol')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();

        // Har bir xom ashyo qaysi mahsulotlar retseptida — bitta so'rov bilan.
        $usedIn = DB::table('recipe_ingredients as ri')
            ->join('recipes as r', 'r.id', '=', 'ri.recipe_id')
            ->leftJoin('bread_categories as b', 'b.id', '=', 'r.bread_category_id')
            ->where('r.shop_id', $shop->getKey())
            ->whereNull('r.deleted_at')
            ->get(['ri.ingredient_id', DB::raw('COALESCE(b.name, r.name) as product')])
            ->groupBy('ingredient_id')
            ->map(fn ($rows) => $rows->pluck('product')->unique()->values()->all());

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('measurementUnit'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nomi')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('price_per_unit')
                    ->label('Narxi (1 birlik)')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ShopInsights::money($state, $shop))
                    ->color(fn ($state) => (float) $state <= 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('unit_label')
                    ->label('Birlik')
                    ->state(fn (Ingredient $r) => $r->measurementUnit?->name_uz ?? $r->unit?->label()),
                Tables\Columns\IconColumn::make('is_flour')
                    ->label('Un')
                    ->boolean(),
                Tables\Columns\TextColumn::make('used_in')
                    ->label('Qaysi retseptlarda')
                    ->state(fn (Ingredient $r) => $usedIn->get($r->getKey(), []))
                    ->badge()
                    ->placeholder('Ishlatilmagan'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Qo\'shilgan')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => static::withUnit($data) + [
                        'currency_id' => $this->shopCurrencyId(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => static::withUnit($data)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions(static::bulkActions())
            ->defaultSort('price_per_unit', 'desc')
            ->paginated(false);
    }

    /** Eski `unit` satr ustunini birlikdan to'ldiradi — API bilan bir xil. */
    private static function withUnit(array $data): array
    {
        $code = MeasurementUnit::query()->whereKey($data['measurement_unit_id'] ?? null)->value('code');
        $data['unit'] = Ingredient::unitFromMeasurementCode($code ?? 'kg');

        return $data;
    }
}
