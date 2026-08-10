<?php

namespace Tests\Feature;

use App\Enums\CustomerOrderStatus;
use App\Enums\NotificationCategory;
use App\Enums\ShopUserType;
use App\Jobs\SendDailyGreetings;
use App\Jobs\SendOrderReminders;
use App\Models\AppNotification;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rejalashtirilgan bildirishnomalar: kunlik tilak (07:00) va bugungi
 * zakaz eslatmasi (03:30, mahalliy vaqt).
 */
class ScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $uzsId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
    }

    private function makeShop(string $name = 'Nonvoyxona'): Shop
    {
        return Shop::create([
            'name' => $name,
            'slug' => 'shop-'.Str::random(6),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);
    }

    private function makeMember(Shop $shop, ShopUserType $type = ShopUserType::Owner): User
    {
        $user = User::factory()->create(['locale' => 'uz']);
        $user->shops()->attach($shop->id, ['user_type' => $type]);

        return $user;
    }

    private function makeOrder(Shop $shop, string $deliveryDate, CustomerOrderStatus $status = CustomerOrderStatus::Active): CustomerOrder
    {
        $customer = Customer::create([
            'shop_id' => $shop->id,
            'name' => 'Mijoz '.Str::random(4),
        ]);

        return CustomerOrder::create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'delivery_date' => $deliveryDate,
            'total_amount' => 100000,
            'created_by' => $this->makeMember($shop)->id,
        ]);
    }

    /** Mahalliy vaqt bo'yicha bugungi sana. */
    private function today(): string
    {
        return now(config('app.business_timezone'))->toDateString();
    }

    // ─── Kunlik tilak ────────────────────────────────────────────────────

    public function test_daily_greeting_reaches_business_members(): void
    {
        $shop = $this->makeShop();
        $owner = $this->makeMember($shop);
        $seller = $this->makeMember($shop, ShopUserType::Seller);

        (new SendDailyGreetings)->handle(app(\App\Services\NotificationService::class));

        foreach ([$owner, $seller] as $user) {
            $this->assertDatabaseHas('app_notifications', [
                'user_id' => $user->id,
                'category' => NotificationCategory::DailyGreeting->value,
            ]);
        }
    }

    public function test_daily_greeting_skips_users_without_business(): void
    {
        $loner = User::factory()->create(['locale' => 'uz']);

        (new SendDailyGreetings)->handle(app(\App\Services\NotificationService::class));

        $this->assertDatabaseMissing('app_notifications', ['user_id' => $loner->id]);
    }

    public function test_daily_greeting_skips_users_who_turned_notifications_off(): void
    {
        $shop = $this->makeShop();
        $off = $this->makeMember($shop);
        $off->update(['notification_prefs' => ['enabled' => false]]);

        $on = $this->makeMember($shop);

        (new SendDailyGreetings)->handle(app(\App\Services\NotificationService::class));

        // O'chirgan foydalanuvchining ro'yxati ham to'lib ketmasligi kerak.
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $off->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $on->id]);
    }

    public function test_daily_greeting_body_is_localised(): void
    {
        $shop = $this->makeShop();
        $user = $this->makeMember($shop);
        $user->update(['locale' => 'ru']);

        (new SendDailyGreetings)->handle(app(\App\Services\NotificationService::class));

        $notification = AppNotification::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(__('notification.daily_greeting.title', [], 'ru'), $notification->title);
        // Kalit topilmasa Lang kalitning o'zini qaytaradi — shunga tushib qolmasin.
        $this->assertStringNotContainsString('notification.', $notification->body);
    }

    // ─── Zakaz eslatmasi ─────────────────────────────────────────────────

    public function test_order_reminder_counts_todays_active_orders(): void
    {
        $shop = $this->makeShop('Baraka nonvoyxona');
        $member = $this->makeMember($shop);

        $this->makeOrder($shop, $this->today());
        $this->makeOrder($shop, $this->today());

        (new SendOrderReminders)->handle(app(\App\Services\NotificationService::class));

        $notification = AppNotification::query()
            ->where('user_id', $member->id)
            ->where('category', NotificationCategory::OrderReminder->value)
            ->firstOrFail();

        $this->assertStringContainsString('2', $notification->body);
        $this->assertStringContainsString('Baraka nonvoyxona', $notification->body);
    }

    public function test_order_reminder_ignores_other_days_and_closed_orders(): void
    {
        $shop = $this->makeShop();
        $member = $this->makeMember($shop);

        $this->makeOrder($shop, now(config('app.business_timezone'))->addDay()->toDateString());
        $this->makeOrder($shop, $this->today(), CustomerOrderStatus::Cancelled);
        $this->makeOrder($shop, $this->today(), CustomerOrderStatus::Delivered);

        (new SendOrderReminders)->handle(app(\App\Services\NotificationService::class));

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $member->id,
            'category' => NotificationCategory::OrderReminder->value,
        ]);
    }

    public function test_order_reminder_only_goes_to_the_shop_with_orders(): void
    {
        $busy = $this->makeShop('Band');
        $quiet = $this->makeShop('Bo\'sh');

        $busyMember = $this->makeMember($busy);
        $quietMember = $this->makeMember($quiet);

        $this->makeOrder($busy, $this->today());

        (new SendOrderReminders)->handle(app(\App\Services\NotificationService::class));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $busyMember->id,
            'category' => NotificationCategory::OrderReminder->value,
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $quietMember->id,
            'category' => NotificationCategory::OrderReminder->value,
        ]);
    }

    public function test_order_reminder_skips_users_who_turned_notifications_off(): void
    {
        $shop = $this->makeShop();
        $off = $this->makeMember($shop);
        $off->update(['notification_prefs' => ['enabled' => false]]);

        $this->makeOrder($shop, $this->today());

        (new SendOrderReminders)->handle(app(\App\Services\NotificationService::class));

        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $off->id,
            'category' => NotificationCategory::OrderReminder->value,
        ]);
    }

    // ─── Komanda va reja ─────────────────────────────────────────────────

    public function test_commands_push_work_to_the_queue(): void
    {
        Queue::fake();

        $this->artisan('notifications:daily-greeting')->assertSuccessful();
        $this->artisan('notifications:order-reminder')->assertSuccessful();

        Queue::assertPushed(SendDailyGreetings::class);
        Queue::assertPushed(SendOrderReminders::class);
    }

    public function test_schedule_runs_at_the_agreed_local_times(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->mapWithKeys(fn ($e) => [$e->command => $e]);

        $reminder = $events->first(fn ($e) => str_contains($e->command, 'notifications:order-reminder'));
        $greeting = $events->first(fn ($e) => str_contains($e->command, 'notifications:daily-greeting'));

        $this->assertNotNull($reminder, 'Zakaz eslatmasi rejaga qo\'shilmagan');
        $this->assertNotNull($greeting, 'Kunlik tilak rejaga qo\'shilmagan');

        $this->assertSame('30 3 * * *', $reminder->expression);
        $this->assertSame('0 7 * * *', $greeting->expression);
        $this->assertSame(config('app.business_timezone'), $reminder->timezone);
        $this->assertSame(config('app.business_timezone'), $greeting->timezone);
    }
}
