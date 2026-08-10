<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kassa kategoriyalari: kirim va chiqim uchun alohida ro'yxat, foydalanuvchi
 * o'zi qo'sha, nomini o'zgartira va o'chira oladi.
 */
class CashCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-'.Str::random(6),
            'is_active' => true,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);

        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/shops/{$this->shop->id}/expense-categories{$suffix}";
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function types(): array
    {
        return [
            'chiqim' => ['expense', 'ijara'],
            'kirim' => ['income', 'sotuv'],
        ];
    }

    #[DataProvider('types')]
    public function test_built_in_categories_are_listed_per_type(string $type, string $expectedKey): void
    {
        $ids = $this->actingAs($this->owner)
            ->getJson($this->url("?type={$type}"))
            ->assertOk()
            ->json('data.categories.*.id');

        $this->assertContains($expectedKey, $ids);
    }

    /** Kirim ro'yxatida chiqim turlari ko'rinmasligi kerak. */
    public function test_income_list_does_not_leak_expense_categories(): void
    {
        $ids = $this->actingAs($this->owner)
            ->getJson($this->url('?type=income'))
            ->assertOk()
            ->json('data.categories.*.id');

        $this->assertNotContains('ijara', $ids);
        $this->assertNotContains('otyin', $ids);
    }

    #[DataProvider('types')]
    public function test_custom_category_is_created_for_its_type(string $type): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson($this->url("?type={$type}"), ['name' => 'Maxsus'])
            ->assertCreated()
            ->assertJsonPath('data.category.is_system', false)
            ->json('data.category.id');

        $this->assertDatabaseHas('expense_categories', ['id' => $id, 'type' => $type]);

        // O'z ro'yxatida ko'rinadi...
        $this->assertContains(
            $id,
            $this->actingAs($this->owner)->getJson($this->url("?type={$type}"))->json('data.categories.*.id'),
        );

        // ...boshqasida yo'q.
        $other = $type === 'income' ? 'expense' : 'income';
        $this->assertNotContains(
            $id,
            $this->actingAs($this->owner)->getJson($this->url("?type={$other}"))->json('data.categories.*.id'),
        );
    }

    public function test_duplicate_name_in_same_type_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->url('?type=expense'), ['name' => 'Reklama'])
            ->assertCreated();

        $this->actingAs($this->owner)
            ->postJson($this->url('?type=expense'), ['name' => 'reklama'])
            ->assertStatus(422);

        // Boshqa turda o'sha nom bo'lishi mumkin.
        $this->actingAs($this->owner)
            ->postJson($this->url('?type=income'), ['name' => 'Reklama'])
            ->assertCreated();
    }

    public function test_custom_category_can_be_renamed(): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson($this->url('?type=expense'), ['name' => 'Eski nom'])
            ->json('data.category.id');

        $this->actingAs($this->owner)
            ->putJson($this->url("/{$id}"), ['name' => 'Yangi nom'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Yangi nom');

        $this->assertDatabaseHas('expense_categories', ['id' => $id, 'name' => 'Yangi nom']);
    }

    public function test_custom_category_can_be_deleted(): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson($this->url('?type=income'), ['name' => 'Vaqtincha'])
            ->json('data.category.id');

        $this->actingAs($this->owner)
            ->deleteJson($this->url("/{$id}"))
            ->assertOk();

        $this->assertDatabaseMissing('expense_categories', ['id' => $id]);
    }

    /** Ishlatilayotgan kategoriya o'chirilsa eski yozuvlar nomsiz qolardi. */
    public function test_category_in_use_cannot_be_deleted(): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson($this->url('?type=expense'), ['name' => 'Reklama'])
            ->json('data.category.id');

        $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/cash", [
                'type' => 'expense',
                'category' => $id,
                'amount' => 10000,
                'date' => now(config('app.business_timezone'))->toDateString(),
            ])->assertCreated();

        $this->actingAs($this->owner)
            ->deleteJson($this->url("/{$id}"))
            ->assertStatus(422);

        $this->assertDatabaseHas('expense_categories', ['id' => $id]);
    }

    public function test_system_category_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs($this->owner)
            ->putJson($this->url('/ijara'), ['name' => 'Boshqa nom'])
            ->assertStatus(404);

        $this->actingAs($this->owner)
            ->deleteJson($this->url('/ijara'))
            ->assertStatus(404);
    }

    /** Ruxsati bor xodim ham boshqaning shaxsiy kategoriyasiga tegmaydi. */
    public function test_other_users_category_is_not_reachable(): void
    {
        $stranger = User::factory()->create();
        $stranger->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
            'permissions' => ['manage_expenses'],
        ]);

        $id = $this->actingAs($this->owner)
            ->postJson($this->url('?type=expense'), ['name' => 'Shaxsiy'])
            ->json('data.category.id');

        $this->actingAs($stranger)
            ->deleteJson($this->url("/{$id}"))
            ->assertStatus(404);

        $this->assertDatabaseHas('expense_categories', ['id' => $id]);
    }

    public function test_existing_rows_default_to_expense_type(): void
    {
        // Migratsiyadan oldin yaratilgan qatorlar xarajat kategoriyasi edi.
        $row = ExpenseCategory::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'name' => 'Eski kategoriya',
        ]);

        $this->assertSame('expense', $row->fresh()->type);
    }
}
