<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Jobs\SendAccessNotices;
use App\Models\AccessNotice;
use App\Models\Shop;
use App\Models\SystemBot;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Muddat ogohlantirishlari.
 *
 * Job har kuni ishlaydi — asosiy talab shu: bir xil xabar ikki marta
 * yuborilmasin, va xabar faqat egalarga borsin.
 */
class AccessNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('access.enabled', true);
        config()->set('access.notice_days', [7, 3, 1, 0, -1]);

        SystemBot::create([
            'name' => 'Register bot',
            'type' => 'register',
            'username' => 'taqseem_register_bot',
            'token' => 'test-token',
            'is_active' => true,
        ]);
    }

    /** @return array{0: User, 1: Shop} */
    private function makeOwner(int $daysLeft, ?int $chatId = 555001): array
    {
        $user = User::factory()->create([
            'telegram_chat_id' => $chatId,
            'locale' => 'uz',
        ]);
        $user->forceFill(['access_until' => now()->addDays($daysLeft)])->save();

        $shop = Shop::create([
            'name' => 'Shop',
            'slug' => 'shop-' . Str::random(6),
            'is_active' => true,
        ]);
        $user->shops()->attach($shop->id, ['user_type' => ShopUserType::Owner]);

        return [$user, $shop];
    }

    private function expectSends(int $times): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->times($times)->andReturn(true);
        $this->app->instance(TelegramBotService::class, $mock);
    }

    public function test_owner_is_warned_seven_days_before(): void
    {
        [$user] = $this->makeOwner(7);
        $this->expectSends(1);

        SendAccessNotices::dispatchSync();

        $this->assertDatabaseHas('access_notices', [
            'user_id' => $user->id,
            'days_before' => 7,
        ]);
    }

    public function test_the_same_notice_is_not_sent_twice(): void
    {
        $this->makeOwner(3);
        $this->expectSends(1);

        SendAccessNotices::dispatchSync();
        SendAccessNotices::dispatchSync();

        $this->assertSame(1, AccessNotice::query()->count());
    }

    /** Muddat uzaytirilsa yangi muddat uchun ogohlantirish qaytadan boshlanadi. */
    public function test_extending_the_period_allows_a_new_notice(): void
    {
        [$user] = $this->makeOwner(3);
        $this->expectSends(2);

        SendAccessNotices::dispatchSync();

        $user->forceFill(['access_until' => now()->addDays(3)->addMonth()])->save();
        $this->travel(1)->months();

        SendAccessNotices::dispatchSync();

        $this->assertSame(2, AccessNotice::query()->count());
    }

    public function test_users_outside_the_notice_days_are_skipped(): void
    {
        $this->makeOwner(5);
        $this->expectSends(0);

        SendAccessNotices::dispatchSync();

        $this->assertSame(0, AccessNotice::query()->count());
    }

    public function test_users_without_telegram_are_skipped(): void
    {
        $this->makeOwner(7, chatId: null);
        $this->expectSends(0);

        SendAccessNotices::dispatchSync();

        $this->assertSame(0, AccessNotice::query()->count());
    }

    /** Xodim tarifni hal qilmaydi — unga xabar bormaydi. */
    public function test_sellers_are_not_warned(): void
    {
        [, $shop] = $this->makeOwner(30);

        $seller = User::factory()->create(['telegram_chat_id' => 555002]);
        $seller->forceFill(['access_until' => now()->addDays(7)])->save();
        $seller->shops()->attach($shop->id, ['user_type' => ShopUserType::Seller]);

        $this->expectSends(0);

        SendAccessNotices::dispatchSync();

        $this->assertSame(0, AccessNotice::query()->count());
    }

    public function test_blocked_users_are_skipped(): void
    {
        [$user] = $this->makeOwner(7);
        $user->forceFill(['blocked_at' => now()])->save();

        $this->expectSends(0);

        SendAccessNotices::dispatchSync();

        $this->assertSame(0, AccessNotice::query()->count());
    }

    public function test_nothing_is_sent_when_the_feature_is_disabled(): void
    {
        config()->set('access.enabled', false);
        $this->makeOwner(7);
        $this->expectSends(0);

        SendAccessNotices::dispatchSync();

        $this->assertSame(0, AccessNotice::query()->count());
    }

    /**
     * Uchta bosqichning matni bir-biridan farq qiladi.
     *
     * Tugash kuni "bugun tugaydi" deyilishi muhim: job soat 10:00 da
     * ishlaydi, muddat esa o'sha kuni kechroq tugashi mumkin — o'shanda
     * "yopildi" deyish noto'g'ri bo'lardi, chunki bo'limlar hali ochiq.
     */
    public function test_each_stage_has_its_own_wording(): void
    {
        $cases = [
            [7, 'kundan keyin tugaydi'],
            [0, 'bugun tugaydi'],
            [-1, 'muddatingiz tugadi'],
        ];

        foreach ($cases as $i => [$days, $needle]) {
            // Har bosqichga alohida foydalanuvchi: yozuvlarni tozalash
            // shart emas, unique kalit takror yuborishni allaqachon
            // to'xtatadi.
            $this->makeOwner($days, chatId: 556000 + $i);

            $captured = null;
            $mock = Mockery::mock(TelegramBotService::class);
            $mock->shouldReceive('sendMessage')
                ->once()
                ->andReturnUsing(function (string $token, int $chatId, string $text) use (&$captured) {
                    $captured = $text;

                    return true;
                });
            $this->app->instance(TelegramBotService::class, $mock);

            SendAccessNotices::dispatchSync();

            $this->assertNotNull($captured, "days={$days} uchun xabar yuborilmadi");
            $this->assertStringContainsString(
                $needle,
                $captured,
                "days={$days} xabarida \"{$needle}\" bo'lishi kerak edi",
            );
        }
    }

    /** Muddat tugagandan keyingi kun ham ogohlantiriladi. */
    public function test_the_day_after_expiry_is_covered(): void
    {
        $this->makeOwner(-1);
        $this->expectSends(1);

        SendAccessNotices::dispatchSync();

        $this->assertDatabaseHas('access_notices', ['days_before' => -1]);
    }

    /** Xabar matni serverda — narx haqida gapirish shu yerda mumkin. */
    public function test_the_message_is_localised_and_names_the_contact(): void
    {
        $this->makeOwner(1);
        config()->set('access.contact', '@taqseem_admin');

        $captured = null;
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function (string $token, int $chatId, string $text) use (&$captured) {
                $captured = $text;

                return true;
            });
        $this->app->instance(TelegramBotService::class, $mock);

        SendAccessNotices::dispatchSync();

        $this->assertStringContainsString('@taqseem_admin', $captured);
        $this->assertStringContainsString('1 kun', $captured);
        $this->assertStringNotContainsString(':days', $captured);
    }
}
