<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Models\Production;
use App\Models\Recipe;
use App\Services\Admin\ShopInsights;
use App\Services\ProductionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ishlab chiqarish tarixi — kun bo'yicha, jami yig'indisi bilan. */
class ProductionsRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'productions';

    protected static ?string $title = 'Ishlab chiqarish';

    protected static ?string $modelLabel = 'ishlab chiqarish';

    protected static ?string $icon = 'heroicon-o-fire';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->productions()->count();
    }

    public function form(Form $form): Form
    {
        $shopId = $this->getOwnerRecord()->getKey();

        return $form->schema([
            Forms\Components\DatePicker::make('date')
                ->label('Sana')
                ->default(now())
                ->required(),
            Forms\Components\Select::make('recipe_id')
                ->label('Retsept (mahsulot)')
                ->options(fn () => Recipe::query()->where('shop_id', $shopId)->with('breadCategory')->get()
                    ->mapWithKeys(fn (Recipe $r) => [$r->id => $r->breadCategory?->name ?? $r->name]))
                ->required(),
            Forms\Components\TextInput::make('batch_count')
                ->label('Partiya soni (qop, blok…)')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->helperText('Chiqqan mahsulot, xarajat va un shu retsept bo\'yicha avtomatik hisoblanadi.'),
            Forms\Components\Textarea::make('notes')
                ->label('Izoh')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();
        $money = fn ($state) => ShopInsights::money($state, $shop);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'breadCategory' => fn ($q) => $q->withTrashed(),
                'creator',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Sana')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('breadCategory.name')
                    ->label('Mahsulot')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('batch_count')
                    ->label('Partiya')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.'))
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')),
                Tables\Columns\TextColumn::make('bread_produced')
                    ->label('Chiqdi (dona)')
                    ->formatStateUsing(fn ($state) => ShopInsights::number($state))
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')
                        ->formatStateUsing(fn ($state) => ShopInsights::number($state))),
                Tables\Columns\TextColumn::make('ingredient_cost')
                    ->label('Xarajat')
                    ->formatStateUsing($money)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')->formatStateUsing($money)),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Hisobiy tushum')
                    ->state(fn (Production $p) => $p->bread_produced * (float) ($p->breadCategory?->selling_price ?? 0))
                    ->formatStateUsing($money),
                Tables\Columns\TextColumn::make('flour_used_kg')
                    ->label('Un (kg)')
                    ->formatStateUsing(fn ($state) => ShopInsights::number($state, 1))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Kim kiritgan')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Izoh')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Kiritilgan vaqt')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bread_category_id')
                    ->label('Mahsulot')
                    ->relationship('breadCategory', 'name', fn (Builder $query) => $query->where('shop_id', $shop->getKey())),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dan'),
                        Forms\Components\DatePicker::make('until')->label('Gacha'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => static::calculate($data) + ['created_by' => auth()->id()]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => static::calculate($data)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions(static::bulkActions())
            ->defaultSort('date', 'desc')
            ->defaultPaginationPageOption(25);
    }

    /**
     * Mahsulot, chiqish, xarajat va unni retsept bo'yicha qayta hisoblaydi —
     * API'dagi ProductionController bilan bir xil. Kassa yozuvini
     * ProductionObserver o'zi yangilaydi.
     */
    private static function calculate(array $data): array
    {
        $recipe = Recipe::query()->with('recipeIngredients.ingredient')->findOrFail($data['recipe_id']);
        $batch = (float) $data['batch_count'];
        $service = app(ProductionService::class);

        return array_merge($data, [
            'bread_category_id' => $recipe->bread_category_id,
            'bread_produced' => $service->calculateBreadOutput($recipe, $batch),
            'ingredient_cost' => $service->calculateIngredientCost($recipe, $batch),
            'flour_used_kg' => $service->calculateFlourUsed($recipe, $batch),
        ]);
    }
}
