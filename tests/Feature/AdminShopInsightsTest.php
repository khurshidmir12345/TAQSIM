<?php

namespace Tests\Feature;

use App\Enums\CashTransactionSource;
use App\Enums\ShopUserType;
use App\Filament\Resources\BreadCategoryResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IngredientResource;
use App\Filament\Resources\ProductionResource;
use App\Filament\Resources\RecipeResource;
use App\Filament\Resources\ShopResource;
use App\Filament\Resources\ShopResource\Pages\ViewShop;
use App\Filament\Resources\ShopResource\RelationManagers;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\ShopsRelationManager;
use App\Models\BreadCategory;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\ShopInsights;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin paneldagi do'kon sahifasi: egasi, faollik, hisobiy natija va
 * har bir yorliqda qo'shish/tahrirlash/o'chirish.
 */
class AdminShopInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private BreadCategory $product;
    private Ingredient $un;
    private Ingredient $tuz;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.email' => 'admin@taqseem.uz']);
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@taqseem.uz',
            'phone' => '+998900000000',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]));

        // Asosiy birliklar seederda yaratiladi — test bazasida yo'q.
        foreach ([['kg', 'ingredient', 'Kilogram'], ['l', 'ingredient', 'Litr'], ['ta', 'ingredient', 'Dona'], ['qop', 'batch', 'Qop']] as $i => [$code, $type, $name]) {
            MeasurementUnit::query()->firstOrCreate(
                ['code' => $code, 'shop_id' => null],
                ['type' => $type, 'name_uz' => $name, 'name_uz_cyrl' => $name, 'name_ru' => $name, 'name_kk' => $name, 'name_ky' => $name, 'name_tr' => $name, 'sort_order' => $i, 'is_active' => true],
            );
        }

        $uzs = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create(['name' => 'Rahmonberdi', 'phone' => '+998901112233']);
        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-' . Str::random(6),
            'is_active' => true,
            'currency_id' => $uzs,
        ]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $this->product = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Kattanon',
            'selling_price' => 3200,
            'currency_id' => $uzs,
        ]);
        $this->un = Ingredient::create([
            'shop_id' => $this->shop->id, 'name' => 'Un', 'unit' => 'kg',
            'price_per_unit' => 5660, 'currency_id' => $uzs,
        ]);
        $this->tuz = Ingredient::create([
            'shop_id' => $this->shop->id, 'name' => 'Tuz', 'unit' => 'kg',
            'price_per_unit' => 2000, 'currency_id' => $uzs,
        ]);
        $this->recipe = Recipe::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $this->product->id,
            'name' => 'Kattanon',
            'output_quantity' => 238,
            'measurement_unit_id' => MeasurementUnit::query()->where('code', 'qop')->value('id'),
            'is_active' => true,
        ]);
        // 1 qop: 50 kg un (283 000) + 1 kg tuz (2 000) = 285 000
        $this->recipe->recipeIngredients()->create(['ingredient_id' => $this->un->id, 'quantity' => 50]);
        $this->recipe->recipeIngredients()->create(['ingredient_id' => $this->tuz->id, 'quantity' => 1]);

        Production::create([
            'shop_id' => $this->shop->id,
            'recipe_id' => $this->recipe->id,
            'bread_category_id' => $this->product->id,
            'date' => now()->toDateString(),
            'batch_count' => 2,
            'flour_used_kg' => 0,
            'bread_produced' => 476,
            'ingredient_cost' => 570000,
            'created_by' => $this->owner->id,
        ]);
    }

    private function manager(string $class): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test($class, ['ownerRecord' => $this->shop, 'pageClass' => ViewShop::class]);
    }

    public function test_insights_compute_activity_and_estimated_result(): void
    {
        $i = ShopInsights::for($this->shop);

        $this->assertSame(1, $i['entries']);
        $this->assertSame(476, $i['produced']);
        $this->assertSame('Faol', $i['status']['label']);
        $this->assertEqualsWithDelta(476 * 3200, $i['revenue'], 0.01);
        $this->assertEqualsWithDelta(476 * 3200 - 570000, $i['profit'], 0.01);
        $this->assertEqualsWithDelta(285000 / 238, ShopInsights::unitCost($this->recipe->load('recipeIngredients.ingredient')), 0.01);
        $this->assertContains('Hech bir xom ashyo "un" deb belgilanmagan — sarflangan un 0 kg chiqadi.', $i['warnings']);
    }

    public function test_status_reports_idle_shop(): void
    {
        $this->assertSame('10 kundan beri jim', ShopInsights::status(1, now()->subDays(10)->toDateString())['label']);
        $this->assertSame('Sozlanmagan', ShopInsights::status(0, null)['label']);
    }

    public function test_shop_view_page_shows_owner_and_summary(): void
    {
        Livewire::test(ViewShop::class, ['record' => $this->shop->getKey()])
            ->assertOk()
            ->assertSee('Rahmonberdi')
            ->assertSee('+998901112233')
            ->assertSee('Hisobiy natija')
            ->assertSee('476 dona');
    }

    public function test_every_tab_renders(): void
    {
        $this->manager(RelationManagers\ProductsRelationManager::class)->assertOk()->assertSee('Kattanon');
        $this->manager(RelationManagers\RecipesRelationManager::class)->assertOk()->assertSee('Un — 50 kg × 5 660 = 283 000 UZS');
        $this->manager(RelationManagers\IngredientsRelationManager::class)->assertOk()->assertSee('Tuz');
        $this->manager(RelationManagers\ProductionsRelationManager::class)->assertOk()->assertSee('476');
        $this->manager(RelationManagers\CashTransactionsRelationManager::class)->assertOk();
        $this->manager(RelationManagers\ReturnsRelationManager::class)->assertOk();
        $this->manager(RelationManagers\ExpensesRelationManager::class)->assertOk();
        $this->manager(RelationManagers\UsersRelationManager::class)->assertOk()->assertSee('Rahmonberdi');
    }

    public function test_admin_creates_production_and_it_is_calculated_from_recipe(): void
    {
        $this->manager(RelationManagers\ProductionsRelationManager::class)
            ->callTableAction(CreateAction::class, data: [
                'date' => now()->toDateString(),
                'recipe_id' => $this->recipe->id,
                'batch_count' => 3,
            ])
            ->assertHasNoTableActionErrors();

        $p = Production::query()->where('batch_count', 3)->firstOrFail();
        $this->assertSame($this->product->id, $p->bread_category_id);
        $this->assertSame(714, $p->bread_produced);
        $this->assertEqualsWithDelta(855000, (float) $p->ingredient_cost, 0.01);
    }

    public function test_admin_edits_and_deletes_product(): void
    {
        $this->manager(RelationManagers\ProductsRelationManager::class)
            ->callTableAction(EditAction::class, $this->product, data: ['selling_price' => 3500])
            ->assertHasNoTableActionErrors();
        $this->assertEqualsWithDelta(3500, (float) $this->product->fresh()->selling_price, 0.01);

        $this->manager(RelationManagers\ProductsRelationManager::class)
            ->callTableAction(DeleteAction::class, $this->product);
        $this->assertSoftDeleted($this->product);
    }

    public function test_admin_creates_ingredient_with_unit_from_measurement(): void
    {
        $this->manager(RelationManagers\IngredientsRelationManager::class)
            ->callTableAction(CreateAction::class, data: [
                'name' => 'Sut',
                'price_per_unit' => 8000,
                'measurement_unit_id' => MeasurementUnit::query()->where('code', 'l')->value('id'),
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $sut = Ingredient::query()->where('name', 'Sut')->firstOrFail();
        $this->assertSame(\App\Enums\IngredientUnit::Litr, $sut->unit);
        $this->assertSame($this->shop->currency_id, $sut->currency_id);
    }

    public function test_second_recipe_for_same_product_is_rejected(): void
    {
        $this->manager(RelationManagers\RecipesRelationManager::class)
            ->callTableAction(CreateAction::class, data: [
                'bread_category_id' => $this->product->id,
                'name' => 'Yana',
                'measurement_unit_id' => MeasurementUnit::query()->where('code', 'qop')->value('id'),
                'output_quantity' => 100,
            ])
            ->assertHasTableActionErrors(['bread_category_id' => 'unique']);
    }

    public function test_only_manual_cash_entries_are_editable(): void
    {
        $manual = CashTransaction::create([
            'shop_id' => $this->shop->id,
            'type' => 'income',
            'source' => CashTransactionSource::Manual->value,
            'category' => 'sotuv',
            'amount' => 100000,
            'date' => now()->toDateString(),
        ]);
        $auto = CashTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('source', CashTransactionSource::Production->value)
            ->first();

        $tab = $this->manager(RelationManagers\CashTransactionsRelationManager::class)
            ->assertTableActionVisible(EditAction::class, $manual);

        if ($auto !== null) {
            $tab->assertTableActionHidden(EditAction::class, $auto);
        }
    }

    public function test_user_page_lists_shops_with_status_and_link(): void
    {
        Livewire::test(ViewUser::class, ['record' => $this->owner->getKey()])
            ->assertOk()
            ->assertSee('Do\'konlari');

        Livewire::test(ShopsRelationManager::class, ['ownerRecord' => $this->owner, 'pageClass' => ViewUser::class])
            ->assertOk()
            ->assertSee('Nonvoyxona')
            ->assertSee('Faol')
            ->assertSee(ShopResource::getUrl('view', ['record' => $this->shop]));
    }

    public function test_operations_and_catalog_are_hidden_from_sidebar(): void
    {
        foreach ([ProductionResource::class, ExpenseResource::class, BreadCategoryResource::class,
            RecipeResource::class, IngredientResource::class,
            \App\Filament\Resources\BreadReturnResource::class] as $resource) {
            $this->assertFalse($resource::shouldRegisterNavigation(), $resource);
        }

        $this->assertTrue(ShopResource::shouldRegisterNavigation());
        $this->assertTrue(UserResource::shouldRegisterNavigation());
    }
}
