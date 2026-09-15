<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private BreadCategory $non;

    protected function setUp(): void
    {
        parent::setUp();

        $uzs = Currency::query()->where('code', 'UZS')->value('id');
        $this->user = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Test',
            'slug' => 'test-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $uzs,
        ]);
        $this->user->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
        $this->non = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Non',
            'selling_price' => 4000,
            'currency_id' => $uzs,
        ]);
    }

    private function base(): string
    {
        return "/api/v1/shops/{$this->shop->id}/outlets";
    }

    private function createOutlet(string $name = 'Bozor do\'koni'): string
    {
        return $this->actingAs($this->user)
            ->postJson($this->base(), [
                'name' => $name,
                'address' => 'Chilonzor',
                'phones' => ['+998901234567', '', '+998901234567', '+998907654321', '+998900000000'],
            ])
            ->assertCreated()
            ->json('data.outlet.id');
    }

    public function test_outlet_crud_with_phones_and_image(): void
    {
        Storage::fake('public');
        $id = $this->createOutlet();

        // Bo'sh va takror raqamlar tashlanadi, 3 tagacha qoladi.
        $show = $this->actingAs($this->user)->getJson($this->base() . "/{$id}")->assertOk();
        $this->assertSame(['+998901234567', '+998907654321', '+998900000000'], $show->json('data.outlet.phones'));
        $this->assertSame(0.0, (float) $show->json('data.outlet.totals.balance'));

        $this->actingAs($this->user)
            ->post($this->base() . "/{$id}/image", ['image' => UploadedFile::fake()->image('shop.jpg')])
            ->assertOk()
            ->assertJsonPath('data.outlet.image_url', fn ($v) => str_contains((string) $v, 'outlets/'));

        $this->actingAs($this->user)
            ->putJson($this->base() . "/{$id}", ['name' => 'Yangi nom', 'phones' => []])
            ->assertOk()
            ->assertJsonPath('data.outlet.name', 'Yangi nom')
            ->assertJsonPath('data.outlet.phones', []);

        $this->actingAs($this->user)->deleteJson($this->base() . "/{$id}")->assertOk();
        $this->actingAs($this->user)->getJson($this->base())->assertOk()->assertJsonCount(0, 'data.outlets');
    }

    public function test_ledger_balance_delivery_return_payment(): void
    {
        $id = $this->createOutlet();
        $entries = $this->base() . "/{$id}/entries";

        // 50 ta non × 4000 = 200 000, shundan 50 000 naqd.
        $res = $this->actingAs($this->user)->postJson($entries, [
            'type' => 'delivery',
            'date' => '2026-09-10',
            'items' => [['bread_category_id' => $this->non->id, 'quantity' => 50]],
            'paid_amount' => 50000,
        ])->assertCreated();
        $this->assertSame(200000.0, (float) $res->json('data.entry.amount'));
        $this->assertSame(150000.0, (float) $res->json('data.outlet.totals.balance'));

        // 5 ta qaytdi (maxsus narx 4000 → 20 000).
        $this->actingAs($this->user)->postJson($entries, [
            'type' => 'return',
            'date' => '2026-09-11',
            'items' => [['bread_category_id' => $this->non->id, 'quantity' => 5, 'unit_price' => 4000]],
        ])->assertCreated()->assertJsonPath('data.outlet.totals.balance', 130000);

        // 100 000 to'ladi.
        $pay = $this->actingAs($this->user)->postJson($entries, [
            'type' => 'payment', 'date' => '2026-09-12', 'amount' => 100000,
        ])->assertCreated();
        $this->assertSame(30000.0, (float) $pay->json('data.outlet.totals.balance'));

        // Ro'yxat: 4 qator (delivery, naqd payment, return, payment), sana filtri ishlaydi.
        $list = $this->actingAs($this->user)->getJson($entries)->assertOk();
        $this->assertCount(4, $list->json('data.entries'));
        $delivery = collect($list->json('data.entries'))->firstWhere('type', 'delivery');
        $this->assertSame('Non', $delivery['items'][0]['name']);

        $filtered = $this->actingAs($this->user)->getJson($entries . '?from=2026-09-12&to=2026-09-12')->assertOk();
        $this->assertCount(1, $filtered->json('data.entries'));

        // Delivery o'chirilsa unga bog'liq naqd to'lov ham ketadi.
        $deliveryId = $res->json('data.entry.id');
        $this->actingAs($this->user)->deleteJson($entries . "/{$deliveryId}")
            ->assertOk()
            ->assertJsonPath('data.outlet.totals.balance', -120000);
        $this->assertCount(2, $this->actingAs($this->user)->getJson($entries)->json('data.entries'));
    }

    public function test_payment_mirrors_to_cash_when_setting_enabled(): void
    {
        $id = $this->createOutlet('Chorsu');
        $entries = $this->base() . "/{$id}/entries";

        $this->actingAs($this->user)->postJson($entries, [
            'type' => 'payment', 'date' => '2026-09-12', 'amount' => 70000,
        ])->assertCreated();

        $row = CashTransaction::query()->where('source', 'outlet')->first();
        $this->assertNotNull($row);
        $this->assertSame(70000.0, (float) $row->amount);
        $this->assertSame('outlet_payment', $row->category);
        $this->assertSame('Chorsu', $row->description);

        // Sozlama o'chirilsa kassadagi aksi yo'qoladi, daftar o'zgarmaydi.
        $this->actingAs($this->user)
            ->putJson("/api/v1/shops/{$this->shop->id}/cash/settings", ['track_outlet_payments' => false])
            ->assertOk()
            ->assertJsonPath('data.settings.track_outlet_payments', false);
        $this->assertSame(0, CashTransaction::query()->where('source', 'outlet')->count());
        $this->assertSame(-70000.0, (float) $this->actingAs($this->user)->getJson($this->base() . "/{$id}")->json('data.outlet.totals.balance'));

        // O'chiq holatda yangi to'lov kassaga yozilmaydi.
        $this->actingAs($this->user)->postJson($entries, [
            'type' => 'payment', 'date' => '2026-09-13', 'amount' => 1000,
        ])->assertCreated();
        $this->assertSame(0, CashTransaction::query()->where('source', 'outlet')->count());

        // Qayta yoqilsa ikkalasi ham kassaga tushadi.
        $this->actingAs($this->user)
            ->putJson("/api/v1/shops/{$this->shop->id}/cash/settings", ['track_outlet_payments' => true])
            ->assertOk();
        $this->assertSame(2, CashTransaction::query()->where('source', 'outlet')->count());
    }

    public function test_daily_report_subtracts_credit_and_adds_payments(): void
    {
        $id = $this->createOutlet();
        $entries = $this->base() . "/{$id}/entries";
        $report = fn (string $date) => $this->actingAs($this->user)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/daily?date={$date}")
            ->assertOk()->json('data.report');

        // Nasiya: 10 × 4000 = 40 000, 15 000 naqd → foyda −25 000.
        $this->actingAs($this->user)->postJson($entries, [
            'type' => 'delivery', 'date' => '2026-09-10',
            'items' => [['bread_category_id' => $this->non->id, 'quantity' => 10]],
            'paid_amount' => 15000,
        ])->assertCreated();
        $r = $report('2026-09-10');
        $this->assertSame(-25000.0, (float) $r['profit']);
        $this->assertSame(25000.0, (float) $r['outlets']['credit']);

        // Keyingi kun to'lov → foyda +25 000.
        $this->actingAs($this->user)->postJson($entries, [
            'type' => 'payment', 'date' => '2026-09-11', 'amount' => 25000,
        ])->assertCreated();
        $this->assertSame(25000.0, (float) $report('2026-09-11')['profit']);

        // Naqd berilgan mahsulot foydani o'zgartirmaydi.
        $this->actingAs($this->user)->postJson($entries, [
            'type' => 'delivery', 'date' => '2026-09-12',
            'items' => [['bread_category_id' => $this->non->id, 'quantity' => 3]],
            'paid_amount' => 12000,
        ])->assertCreated();
        $this->assertSame(0.0, (float) $report('2026-09-12')['profit']);
    }

    public function test_other_shops_outlet_is_not_visible(): void
    {
        $id = $this->createOutlet();
        $other = Shop::create([
            'name' => 'Other', 'slug' => 'o-' . Str::random(5), 'is_active' => true,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);
        $this->user->shops()->attach($other->id, ['user_type' => ShopUserType::Owner]);

        $this->actingAs($this->user)->getJson("/api/v1/shops/{$other->id}/outlets/{$id}")->assertNotFound();
        $this->actingAs($this->user)->postJson("/api/v1/shops/{$other->id}/outlets/{$id}/entries", [
            'type' => 'payment', 'date' => '2026-09-12', 'amount' => 10,
        ])->assertNotFound();
    }
}
