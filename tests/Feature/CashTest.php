<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\BreadReturn;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kassa daftari: tashqi kirim/chiqim + mahsulot chiqimi va vozvratdan
 * avtomatik ko'chirilgan yozuvlar.
 */
class CashTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private BreadCategory $category;

    private string $uzsId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create();

        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-'.Str::random(6),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $this->category = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Oq non',
            'selling_price' => 5000,
            'currency_id' => $this->uzsId,
        ]);
    }

    private function today(): string
    {
        return now(config('app.business_timezone'))->toDateString();
    }

    private function makeProduction(int $bread = 100, float $cost = 200000, ?string $date = null): Production
    {
        $recipe = Recipe::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $this->category->id,
            'name' => 'Retsept',
            'flour_amount_kg' => 50,
            'output_quantity' => 100,
            'is_active' => true,
        ]);

        return Production::create([
            'shop_id' => $this->shop->id,
            'recipe_id' => $recipe->id,
            'bread_category_id' => $this->category->id,
            'date' => $date ?? $this->today(),
            'batch_count' => 1,
            'flour_used_kg' => 50,
            'bread_produced' => $bread,
            'ingredient_cost' => $cost,
            'created_by' => $this->owner->id,
        ]);
    }

    private function makeReturn(int $qty = 10, float $total = 50000, ?string $date = null): BreadReturn
    {
        return BreadReturn::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $this->category->id,
            'date' => $date ?? $this->today(),
            'quantity' => $qty,
            'price_per_unit' => $total / max(1, $qty),
            'total_amount' => $total,
            'created_by' => $this->owner->id,
        ]);
    }

    // ─── Avtomatik ko'chirish ────────────────────────────────────────────

    public function test_production_creates_income_and_cost_entries(): void
    {
        $production = $this->makeProduction(bread: 100, cost: 200000);

        // 100 dona × 5000 = 500 000 kirim, 200 000 xom ashyo chiqimi.
        $this->assertDatabaseHas('cash_transactions', [
            'source' => 'production',
            'source_id' => $production->id,
            'type' => 'income',
            'amount' => 500000.00,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'source' => 'production',
            'source_id' => $production->id,
            'type' => 'expense',
            'amount' => 200000.00,
        ]);
    }

    public function test_return_creates_expense_entry(): void
    {
        $return = $this->makeReturn(total: 50000);

        $this->assertDatabaseHas('cash_transactions', [
            'source' => 'return',
            'source_id' => $return->id,
            'type' => 'expense',
            'amount' => 50000.00,
        ]);
    }

    public function test_editing_production_updates_mirrored_amounts(): void
    {
        $production = $this->makeProduction(bread: 100, cost: 200000);

        $production->update(['bread_produced' => 200, 'ingredient_cost' => 300000]);

        $this->assertDatabaseHas('cash_transactions', [
            'source_id' => $production->id,
            'type' => 'income',
            'amount' => 1000000.00,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'source_id' => $production->id,
            'type' => 'expense',
            'amount' => 300000.00,
        ]);
        // Eski summalar qolib ketmasligi kerak.
        $this->assertSame(2, CashTransaction::where('source_id', $production->id)->count());
    }

    public function test_deleting_source_removes_mirrored_entries(): void
    {
        $production = $this->makeProduction();
        $return = $this->makeReturn();

        $production->delete();
        $return->delete();

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_entries_are_not_created_when_setting_is_off(): void
    {
        $this->shop->update(['cash_track_production' => false, 'cash_track_returns' => false]);

        $this->makeProduction();
        $this->makeReturn();

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_turning_setting_on_backfills_existing_records(): void
    {
        $this->shop->update(['cash_track_production' => false, 'cash_track_returns' => false]);

        $this->makeProduction();
        $this->makeReturn();
        $this->assertSame(0, CashTransaction::count());

        $this->actingAs($this->owner)
            ->putJson("/api/v1/shops/{$this->shop->id}/cash/settings", [
                'track_production' => true,
                'track_returns' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.track_production', true);

        // Sozlama yoqilgach eski yozuvlar ham kassaga tushishi kerak.
        $this->assertSame(3, CashTransaction::count());
    }

    public function test_turning_setting_off_removes_mirrored_entries(): void
    {
        $this->makeProduction();
        $this->makeReturn();
        $this->assertSame(3, CashTransaction::count());

        $this->actingAs($this->owner)
            ->putJson("/api/v1/shops/{$this->shop->id}/cash/settings", [
                'track_production' => false,
            ])
            ->assertOk();

        // Faqat vozvrat yozuvi qoladi.
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame(0, CashTransaction::where('source', 'production')->count());
    }

    // ─── Xulosa ─────────────────────────────────────────────────────────

    public function test_summary_counts_income_expense_and_net(): void
    {
        $this->makeProduction(bread: 100, cost: 200000);   // +500 000 / −200 000
        $this->makeReturn(total: 50000);                    // −50 000

        $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/cash", [
                'type' => 'expense',
                'category' => 'ijara',
                'amount' => 100000,
                'date' => $this->today(),
            ])
            ->assertCreated();

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash?period=day")
            ->assertOk();

        $response->assertJsonPath('data.summary.income.total', 500000)
            ->assertJsonPath('data.summary.expense.total', 350000)
            ->assertJsonPath('data.summary.net', 150000)
            ->assertJsonPath('data.summary.is_profit', true);
    }

    public function test_summary_reports_loss(): void
    {
        $this->makeProduction(bread: 10, cost: 200000);  // +50 000 / −200 000

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash?period=day")
            ->assertOk()
            ->assertJsonPath('data.summary.net', -150000)
            ->assertJsonPath('data.summary.is_profit', false);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function periods(): array
    {
        return [
            'kunlik' => [CashService::PERIOD_DAY],
            'haftalik' => [CashService::PERIOD_WEEK],
            'oylik' => [CashService::PERIOD_MONTH],
        ];
    }

    #[DataProvider('periods')]
    public function test_every_period_returns_a_summary(string $period): void
    {
        $this->makeProduction();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash?period={$period}")
            ->assertOk()
            ->assertJsonPath('data.period', $period)
            ->assertJsonStructure(['data' => ['summary' => ['income', 'expense', 'net'], 'entries', 'settings']]);
    }

    public function test_older_entries_fall_outside_the_day_period(): void
    {
        $this->makeProduction(date: now(config('app.business_timezone'))->subDays(5)->toDateString());

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash?period=day")
            ->assertOk()
            ->assertJsonPath('data.summary.income.total', 0)
            ->assertJsonCount(0, 'data.entries');
    }

    // ─── Qo'lda yozuvlar ────────────────────────────────────────────────

    public function test_manual_income_is_created_and_listed(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/cash", [
                'type' => 'income',
                'category' => 'sotuv',
                'amount' => 75000,
                'description' => 'Qo\'shimcha sotuv',
                'date' => $this->today(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.entry.type', 'income')
            ->assertJsonPath('data.entry.is_editable', true);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash?period=day")
            ->assertOk()
            ->assertJsonPath('data.summary.income.total', 75000);
    }

    /** Xarajat eski jadvalga yozilsin — eski ilova uni ko'rishda davom etadi. */
    public function test_manual_expense_is_stored_in_expenses_table(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/cash", [
                'type' => 'expense',
                'category' => 'gaz',
                'amount' => 40000,
                'date' => $this->today(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'shop_id' => $this->shop->id,
            'category' => 'gaz',
            'amount' => 40000,
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/expenses")
            ->assertOk()
            ->assertJsonCount(1, 'data.expenses');
    }

    public function test_manual_entry_can_be_edited_and_deleted(): void
    {
        $created = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/cash", [
                'type' => 'income',
                'category' => 'sotuv',
                'amount' => 10000,
                'date' => $this->today(),
            ])->json('data.entry.id');

        $this->actingAs($this->owner)
            ->putJson("/api/v1/shops/{$this->shop->id}/cash/{$created}", ['amount' => 25000])
            ->assertOk()
            ->assertJsonPath('data.entry.amount', 25000);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/cash/{$created}")
            ->assertOk();

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_automatic_entry_cannot_be_edited_or_deleted(): void
    {
        $production = $this->makeProduction();
        $auto = CashTransaction::where('source_id', $production->id)->firstOrFail();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/shops/{$this->shop->id}/cash/{$auto->id}", ['amount' => 1])
            ->assertStatus(422);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/cash/{$auto->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('cash_transactions', ['id' => $auto->id]);
    }

    public function test_entries_expose_translated_category_names(): void
    {
        $this->makeProduction();

        $entries = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash?period=day")
            ->assertOk()
            ->json('data.entries');

        $names = array_column($entries, 'category_name');

        // Nom foydalanuvchi tilida qaytadi, kalitning o'zi emas.
        $this->assertContains(__('cash.auto_categories.production_income'), $names);
        $this->assertNotContains('cash.auto_categories.production_income', $names);
    }

    public function test_other_shop_member_cannot_read_cash(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/shops/{$this->shop->id}/cash")
            ->assertStatus(403);
    }

    // ─── Asosiy sahifa ──────────────────────────────────────────────────

    /**
     * Tashqi xarajat endi asosiy sahifadagi foydaga tegmaydi — u kassaning
     * ishi. Aks holda bir kun ijara to'langani uchun nonvoyxona zarar
     * ko'rsatgandek ko'rinardi.
     */
    public function test_external_expense_no_longer_changes_dashboard_profit(): void
    {
        $this->makeProduction(bread: 100, cost: 200000);   // 500 000 − 200 000

        $before = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/daily?date={$this->today()}")
            ->assertOk()
            ->json('data.report.profit');

        $this->assertSame(300000.0, (float) $before);

        $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/cash", [
                'type' => 'expense',
                'category' => 'ijara',
                'amount' => 100000,
                'date' => $this->today(),
            ])->assertCreated();

        $after = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/daily?date={$this->today()}")
            ->assertOk()
            ->json('data.report.profit');

        $this->assertSame(300000.0, (float) $after, 'Tashqi xarajat asosiy foydani o\'zgartirmasligi kerak');
    }
}
