<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\ShopResource\RelationManagers\Concerns\AdminEditable;
use App\Models\BreadReturn;
use App\Models\Production;
use App\Services\Admin\ShopInsights;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Qaytgan mahsulotlar — har biri bitta ishlab chiqarish yozuviga bog'langan. */
class ReturnsRelationManager extends RelationManager
{
    use AdminEditable;

    protected static string $relationship = 'breadReturns';

    protected static ?string $title = 'Qaytishlar';

    protected static ?string $modelLabel = 'qaytish';

    protected static ?string $icon = 'heroicon-o-arrow-uturn-left';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->breadReturns()->count();
    }

    public function form(Form $form): Form
    {
        $shopId = $this->getOwnerRecord()->getKey();

        return $form->schema([
            Forms\Components\Select::make('production_id')
                ->label('Qaysi ishlab chiqarishdan')
                ->options(fn () => Production::query()
                    ->where('shop_id', $shopId)
                    ->with(['breadCategory' => fn ($q) => $q->withTrashed()])
                    ->latest('date')
                    ->limit(200)
                    ->get()
                    ->mapWithKeys(fn (Production $p) => [$p->id => $p->date->format('d.m.Y') . ' — '
                        . ($p->breadCategory?->name ?? '?') . ' (' . $p->bread_produced . ' dona)']))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    $price = Production::query()->with('breadCategory')->find($state)?->breadCategory?->selling_price;
                    if ($price !== null) {
                        $set('price_per_unit', $price);
                    }
                }),
            Forms\Components\TextInput::make('quantity')
                ->label('Soni (dona)')
                ->integer()
                ->minValue(1)
                ->required(),
            Forms\Components\TextInput::make('price_per_unit')
                ->label('Dona narxi')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Textarea::make('reason')
                ->label('Sabab')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $shop = $this->getOwnerRecord();
        $money = fn ($state) => ShopInsights::money($state, $shop);
        // Do'kon daftaridan kelgan qaytish o'sha yerda boshqariladi.
        $own = fn (BreadReturn $r) => ! $r->isFromOutlet();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('breadCategory'))
            ->columns([
                Tables\Columns\TextColumn::make('date')->label('Sana')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('breadCategory.name')->label('Mahsulot')->placeholder('—'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Soni')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')),
                Tables\Columns\TextColumn::make('price_per_unit')->label('Narxi')->formatStateUsing($money),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Summa')
                    ->formatStateUsing($money)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Jami')->formatStateUsing($money)),
                Tables\Columns\TextColumn::make('reason')->label('Sabab')->placeholder('—')->limit(40),
                Tables\Columns\IconColumn::make('outlet_entry_id')
                    ->label('Do\'kondan')
                    ->boolean()
                    ->state(fn (BreadReturn $r) => $r->isFromOutlet()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => static::fromProduction($data) + ['created_by' => auth()->id()]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible($own)
                    ->mutateFormDataUsing(fn (array $data) => static::fromProduction($data)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions(static::bulkActions())
            ->defaultSort('date', 'desc')
            ->defaultPaginationPageOption(25);
    }

    /** Mahsulot va sana ishlab chiqarishdan olinadi, summa hisoblanadi — API bilan bir xil. */
    private static function fromProduction(array $data): array
    {
        $production = Production::query()->findOrFail($data['production_id']);

        return array_merge($data, [
            'bread_category_id' => $production->bread_category_id,
            'date' => $production->date->toDateString(),
            'total_amount' => (int) $data['quantity'] * (float) $data['price_per_unit'],
        ]);
    }
}
