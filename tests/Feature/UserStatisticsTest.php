<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Statistics\ActiveUsersStats;
use App\Filament\Pages\Statistics\ConfiguredNotStartedStats;
use App\Filament\Pages\Statistics\NewUsersStats;
use App\Filament\Pages\Statistics\StatisticsPage;
use App\Models\BreadCategory;
use App\Models\BreadReturn;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\UserStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin paneldagi foydalanuvchi statistikasi.
 */
class UserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private UserStatisticsService $stats;

    private string $uzsId;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stats = app(UserStatisticsService::class);
        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->tz = config('app.business_timezone');
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tz)->startOfDay();
    }

    private function makeUser(?CarbonImmutable $registeredAt = null): User
    {
        $user = User::factory()->create();

        if ($registeredAt !== null) {
            $user->forceFill(['created_at' => $registeredAt->utc()])->save();
        }

        return $user->fresh();
    }

    private function makeShop(?CarbonImmutable $createdAt = null): Shop
    {
        $shop = Shop::create([
            'name' => 'Do\'kon '.Str::random(4),
            'slug' => 'shop-'.Str::random(6),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        if ($createdAt !== null) {
            $shop->forceFill(['created_at' => $createdAt->utc()])->save();
        }

        return $shop->fresh();
    }

    private function makeCategory(Shop $shop): BreadCategory
    {
        return BreadCategory::create([
            'shop_id' => $shop->id,
            'name' => 'Oq non',
            'selling_price' => 5000,
            'currency_id' => $this->uzsId,
        ]);
    }

    private function makeRecipe(Shop $shop, ?BreadCategory $category = null): Recipe
    {
        return Recipe::create([
            'shop_id' => $shop->id,
            'bread_category_id' => ($category ?? $this->makeCategory($shop))->id,
            'name' => 'Retsept '.Str::random(4),
            'flour_amount_kg' => 50,
            'output_quantity' => 100,
            'is_active' => true,
        ]);
    }

    private function makeProduction(Shop $shop, User $by, CarbonImmutable $at): Production
    {
        $category = $this->makeCategory($shop);

        $production = Production::create([
            'shop_id' => $shop->id,
            'recipe_id' => $this->makeRecipe($shop, $category)->id,
            'bread_category_id' => $category->id,
            'date' => $at->toDateString(),
            'batch_count' => 1,
            'flour_used_kg' => 50,
            'bread_produced' => 100,
            'created_by' => $by->id,
        ]);

        $production->forceFill(['created_at' => $at->utc()])->save();

        return $production;
    }

    // ─── Yangi foydalanuvchilar ──────────────────────────────────────────

    public function test_new_users_are_counted_in_their_local_day(): void
    {
        $today = $this->today();

        $this->makeUser($today->setTime(9, 0));
        $this->makeUser($today->setTime(23, 30));   // UTC'da ertangi kun
        $this->makeUser($today->subDay()->setTime(10, 0));

        $series = $this->stats->newUsers(
            UserStatisticsService::DAILY,
            $today->subDay()->toDateString(),
            $today->toDateString(),
        );

        // Kechqurun 23:30 da kelgan foydalanuvchi bugungi kunda qolishi kerak —
        // UTC bo'yicha hisoblansa ertaga tushib ketardi.
        $this->assertSame(2, $series[$today->format('Y-m-d')]);
        $this->assertSame(1, $series[$today->subDay()->format('Y-m-d')]);
    }

    public function test_new_users_monthly_buckets(): void
    {
        $thisMonth = $this->today()->startOfMonth();

        $this->makeUser($thisMonth->addDays(2));
        $this->makeUser($thisMonth->addDays(5));
        $this->makeUser($thisMonth->subMonth()->addDays(3));

        $series = $this->stats->newUsers(
            UserStatisticsService::MONTHLY,
            $thisMonth->subMonth()->toDateString(),
            $this->today()->toDateString(),
        );

        $this->assertSame(2, $series[$thisMonth->format('Y-m')]);
        $this->assertSame(1, $series[$thisMonth->subMonth()->format('Y-m')]);
    }

    public function test_deleted_accounts_still_count_as_registrations(): void
    {
        $today = $this->today();
        $user = $this->makeUser($today->setTime(8, 0));
        $user->delete();

        $series = $this->stats->newUsers(
            UserStatisticsService::DAILY,
            $today->toDateString(),
            $today->toDateString(),
        );

        $this->assertSame(1, $series[$today->format('Y-m-d')]);
        $this->assertSame(1, $this->stats->deletedUsers($today->toDateString(), $today->toDateString()));
    }

    public function test_users_outside_the_range_are_excluded(): void
    {
        $today = $this->today();
        $this->makeUser($today->subDays(10));

        $series = $this->stats->newUsers(
            UserStatisticsService::DAILY,
            $today->subDays(2)->toDateString(),
            $today->toDateString(),
        );

        $this->assertSame(0, array_sum($series));
    }

    // ─── Faol foydalanuvchilar ───────────────────────────────────────────

    public function test_active_users_count_production_and_returns(): void
    {
        $today = $this->today();
        $shop = $this->makeShop();

        $baker = $this->makeUser();
        $seller = $this->makeUser();

        $this->makeProduction($shop, $baker, $today->setTime(4, 0));

        BreadReturn::create([
            'shop_id' => $shop->id,
            'bread_category_id' => $this->makeCategory($shop)->id,
            'date' => $today->toDateString(),
            'quantity' => 5,
            'price_per_unit' => 4000,
            'total_amount' => 20000,
            'created_by' => $seller->id,
        ])->forceFill(['created_at' => $today->setTime(18, 0)->utc()])->save();

        $series = $this->stats->activeUsers(
            UserStatisticsService::DAILY,
            $today->toDateString(),
            $today->toDateString(),
        );

        $this->assertSame(2, $series[$today->format('Y-m-d')]);
        $this->assertSame(2, $this->stats->activeUsersTotal($today->toDateString(), $today->toDateString()));
    }

    public function test_same_user_counted_once_per_bucket(): void
    {
        $today = $this->today();
        $shop = $this->makeShop();
        $baker = $this->makeUser();

        $this->makeProduction($shop, $baker, $today->setTime(4, 0));
        $this->makeProduction($shop, $baker, $today->setTime(15, 0));

        $series = $this->stats->activeUsers(
            UserStatisticsService::DAILY,
            $today->toDateString(),
            $today->toDateString(),
        );

        $this->assertSame(1, $series[$today->format('Y-m-d')]);
    }

    public function test_unique_total_differs_from_bucket_sum(): void
    {
        $today = $this->today();
        $shop = $this->makeShop();
        $baker = $this->makeUser();

        $this->makeProduction($shop, $baker, $today->subDay()->setTime(5, 0));
        $this->makeProduction($shop, $baker, $today->setTime(5, 0));

        $from = $today->subDay()->toDateString();
        $to = $today->toDateString();

        $series = $this->stats->activeUsers(UserStatisticsService::DAILY, $from, $to);

        // Ikki kun faol — kataklar yig'indisi 2, lekin odam bitta.
        $this->assertSame(2, array_sum($series));
        $this->assertSame(1, $this->stats->activeUsersTotal($from, $to));
    }

    public function test_expense_alone_does_not_make_a_user_active(): void
    {
        $today = $this->today();
        $shop = $this->makeShop();
        $accountant = $this->makeUser();

        Expense::create([
            'shop_id' => $shop->id,
            'category' => 'other',
            'amount' => 50000,
            'date' => $today->toDateString(),
            'created_by' => $accountant->id,
        ])->forceFill(['created_at' => $today->setTime(12, 0)->utc()])->save();

        $this->assertSame(0, $this->stats->activeUsersTotal($today->toDateString(), $today->toDateString()));
    }

    // ─── Sozlagan, lekin ishlamagan ──────────────────────────────────────

    private function configureShop(Shop $shop): void
    {
        $this->makeCategory($shop);

        Ingredient::create([
            'shop_id' => $shop->id,
            'name' => 'Un',
            'is_flour' => true,
            'price_per_unit' => 6000,
            'currency_id' => $this->uzsId,
        ]);
    }

    public function test_configured_shop_without_operations_is_listed(): void
    {
        $shop = $this->makeShop($this->today()->subDays(3));
        $this->configureShop($shop);

        $this->assertSame(1, $this->stats->configuredNotStartedOwners());
    }

    public function test_empty_shop_without_configuration_is_not_listed(): void
    {
        $this->makeShop($this->today()->subDays(3));

        // Hech narsa kiritilmagan — bu "sozlagan" emas.
        $this->assertSame(0, $this->stats->configuredNotStartedOwners());
    }

    /**
     * Har qanday operatsiya do'konni ro'yxatdan chiqarishi kerak.
     *
     * @return array<string,array{0:string}>
     */
    public static function operationTypes(): array
    {
        return [
            'kirim' => ['production'],
            'vozvrat' => ['return'],
            'xarajat' => ['expense'],
            'zakaz' => ['order'],
        ];
    }

    #[DataProvider('operationTypes')]
    public function test_any_operation_removes_shop_from_the_list(string $operation): void
    {
        $shop = $this->makeShop($this->today()->subDays(3));
        $this->configureShop($shop);
        $user = $this->makeUser();

        match ($operation) {
            'production' => $this->makeProduction($shop, $user, $this->today()),
            'return' => BreadReturn::create([
                'shop_id' => $shop->id,
                'bread_category_id' => $this->makeCategory($shop)->id,
                'date' => $this->today()->toDateString(),
                'quantity' => 1,
                'price_per_unit' => 1000,
                'total_amount' => 1000,
                'created_by' => $user->id,
            ]),
            'expense' => Expense::create([
                'shop_id' => $shop->id,
                'category' => 'other',
                'amount' => 1000,
                'date' => $this->today()->toDateString(),
                'created_by' => $user->id,
            ]),
            'order' => CustomerOrder::create([
                'shop_id' => $shop->id,
                'customer_id' => Customer::create(['shop_id' => $shop->id, 'name' => 'Mijoz'])->id,
                'status' => 'active',
                'delivery_date' => $this->today()->toDateString(),
                'total_amount' => 1000,
                'created_by' => $user->id,
            ]),
        };

        $this->assertSame(0, $this->stats->configuredNotStartedOwners());
    }

    public function test_recipe_alone_counts_as_configured(): void
    {
        $shop = $this->makeShop($this->today()->subDay());
        $this->makeRecipe($shop);

        $this->assertSame(1, $this->stats->configuredNotStartedOwners());
    }

    public function test_configured_not_started_series_uses_shop_creation_date(): void
    {
        $today = $this->today();

        $old = $this->makeShop($today->subDays(2));
        $this->configureShop($old);

        $fresh = $this->makeShop($today);
        $this->configureShop($fresh);

        $series = $this->stats->configuredNotStarted(
            UserStatisticsService::DAILY,
            $today->subDays(2)->toDateString(),
            $today->toDateString(),
        );

        $this->assertSame(1, $series[$today->subDays(2)->format('Y-m-d')]);
        $this->assertSame(0, $series[$today->subDay()->format('Y-m-d')]);
        $this->assertSame(1, $series[$today->format('Y-m-d')]);
    }

    // ─── Davr tanlovi ────────────────────────────────────────────────────

    public function test_range_presets_resolve_to_expected_dates(): void
    {
        $today = $this->today();

        $this->assertSame(
            [$today->toDateString(), $today->toDateString()],
            StatisticsPage::resolveRange('today'),
        );

        $this->assertSame(
            [$today->subDays(6)->toDateString(), $today->toDateString()],
            StatisticsPage::resolveRange('7d'),
        );

        $this->assertSame(
            [$today->startOfMonth()->toDateString(), $today->toDateString()],
            StatisticsPage::resolveRange('this_month'),
        );

        $this->assertSame(
            [
                $today->subMonth()->startOfMonth()->toDateString(),
                $today->subMonth()->endOfMonth()->toDateString(),
            ],
            StatisticsPage::resolveRange('last_month'),
        );
    }

    public function test_daily_buckets_are_continuous(): void
    {
        $today = $this->today();

        $buckets = $this->stats->buckets(
            UserStatisticsService::DAILY,
            $today->subDays(4)->toDateString(),
            $today->toDateString(),
        );

        $this->assertCount(5, $buckets);
        $this->assertSame($today->subDays(4)->format('Y-m-d'), $buckets[0]);
        $this->assertSame($today->format('Y-m-d'), end($buckets));
    }

    // ─── Sahifalar ochiladimi ────────────────────────────────────────────

    private function actingAsAdmin(): void
    {
        config(['admin.email' => 'admin@taqseem.uz']);

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@taqseem.uz',
            'phone' => '+998900000000',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]));
    }

    /**
     * @return array<string,array{0:class-string}>
     */
    public static function statisticsPages(): array
    {
        return [
            'yangi foydalanuvchilar' => [NewUsersStats::class],
            'faol foydalanuvchilar' => [ActiveUsersStats::class],
            'sozlagan, ishlamagan' => [ConfiguredNotStartedStats::class],
        ];
    }

    #[DataProvider('statisticsPages')]
    public function test_statistics_page_renders(string $page): void
    {
        $this->actingAsAdmin();

        Livewire::test($page)->assertOk();
    }

    public function test_dashboard_does_not_show_statistics_widgets(): void
    {
        $this->actingAsAdmin();

        $widgets = (new Dashboard)->getWidgets();

        foreach ($widgets as $widget) {
            $this->assertStringNotContainsString(
                'Widgets\\Statistics',
                $widget,
                'Statistika vidjeti umumiy dashboardga tushib qolgan',
            );
        }
    }
}
