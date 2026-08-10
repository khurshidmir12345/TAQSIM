<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Jobs\SendBulkNotification;
use App\Models\AppNotification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Khurshid',
            'phone' => '+998901234567',
            'password' => 'secret123',
            'is_accepted_policy' => true,
            'phone_verified_at' => now(),
            'locale' => 'uz',
        ]);
    }

    /**
     * `user_devices.token_id` haqiqiy Sanctum tokeniga bog'langan (FK),
     * shuning uchun qurilma yozuvi uchun token ham yaratiladi.
     */
    private function makeDevice(User $user, ?string $pushToken = null): UserDevice
    {
        $token = $user->createToken('device');

        return UserDevice::create([
            'user_id' => $user->id,
            'token_id' => $token->accessToken->getKey(),
            'push_token' => $pushToken,
        ]);
    }

    private function makeNotification(array $attributes = []): AppNotification
    {
        return AppNotification::create(array_merge([
            'user_id' => $this->user->id,
            'category' => NotificationCategory::Admin,
            'title' => 'Sarlavha',
            'body' => 'Matn',
        ], $attributes));
    }

    // ─── Ro'yxat va o'qilgan holati ──────────────────────────────────────

    public function test_list_returns_notifications_with_unread_count(): void
    {
        $this->makeNotification();
        $this->makeNotification(['read_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(2, 'data.notifications');
    }

    public function test_user_sees_only_own_notifications(): void
    {
        $other = User::create([
            'name' => 'Boshqa',
            'phone' => '+998901112233',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        AppNotification::create([
            'user_id' => $other->id,
            'category' => NotificationCategory::Admin,
            'title' => 'Begona',
            'body' => 'Ko\'rinmasligi kerak',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.notifications');
    }

    public function test_mark_read_updates_unread_count(): void
    {
        $notification = $this->makeNotification();

        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_another_users_notification_read(): void
    {
        $other = User::create([
            'name' => 'Boshqa',
            'phone' => '+998901112244',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        $foreign = AppNotification::create([
            'user_id' => $other->id,
            'category' => NotificationCategory::Admin,
            'title' => 'Begona',
            'body' => 'Matn',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$foreign->id}/read")
            ->assertStatus(404);

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $this->makeNotification();
        $this->makeNotification();

        $this->actingAs($this->user)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, AppNotification::where('user_id', $this->user->id)->unread()->count());
    }

    // ─── Sozlamalar ──────────────────────────────────────────────────────

    public function test_preferences_default_to_enabled(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.preferences.enabled', true)
            ->assertJsonPath('data.preferences.daily_greeting', true)
            ->assertJsonPath('data.preferences.order_reminder', true);
    }

    public function test_disabling_switches_off_optional_categories_only(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/notifications/preferences', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.preferences.enabled', false)
            // Eslatuvchi turlar umumiy tugmaga ergashadi...
            ->assertJsonPath('data.preferences.daily_greeting', false)
            ->assertJsonPath('data.preferences.order_reminder', false)
            // ...majburiy turlar esa doim yoqiq ko'rinadi.
            ->assertJsonPath('data.preferences.employee_added', true)
            ->assertJsonPath('data.preferences.system', true);

        $this->assertFalse($this->user->fresh()->notification_prefs['enabled']);
    }

    /**
     * Foydalanuvchi qo'lidagi eski ilova tur bo'yicha kalitlarni hamon
     * yuboradi — so'rov rad etilmasligi, lekin holatga ta'sir qilmasligi kerak.
     */
    public function test_legacy_category_keys_are_accepted_but_ignored(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/notifications/preferences', ['daily_greeting' => false])
            ->assertOk()
            ->assertJsonPath('data.preferences.enabled', true)
            ->assertJsonPath('data.preferences.daily_greeting', true);

        $this->assertArrayNotHasKey(
            'daily_greeting',
            $this->user->fresh()->notification_prefs ?? [],
        );
    }

    // ─── Push sozlamasi mantiqi ──────────────────────────────────────────

    public function test_notification_is_recorded_even_when_push_disabled(): void
    {
        $this->user->update(['notification_prefs' => ['enabled' => false]]);

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            // Push YUBORILMAYDI...
            $mock->shouldNotReceive('send');
        });

        app(NotificationService::class)->notifyRaw(
            $this->user->fresh(),
            NotificationCategory::DailyGreeting,
            'Xayrli tong',
            'Ishingizga baraka',
        );

        // ...lekin yozuv baribir yaratiladi.
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->user->id,
            'title' => 'Xayrli tong',
        ]);
    }

    public function test_admin_message_ignores_disabled_preference(): void
    {
        $this->user->update(['notification_prefs' => ['enabled' => false]]);

        $this->makeDevice($this->user, 'device-token-1');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            // Admin xabari o'chirib bo'lmaydigan tur — yuborilishi shart.
            $mock->shouldReceive('send')->once()->andReturn(FcmService::SENT);
        });

        app(NotificationService::class)->notifyRaw(
            $this->user->fresh(),
            NotificationCategory::Admin,
            'Muhim',
            'Admin xabari',
        );
    }

    /**
     * Xodim qo'shilishi va tizim xabari hisobga oid muhim ma'lumot —
     * foydalanuvchi bildirishnomani o'chirsa ham yetkaziladi.
     *
     * @return array<int,array{0:NotificationCategory}>
     */
    public static function mandatoryCategories(): array
    {
        return [
            [NotificationCategory::EmployeeAdded],
            [NotificationCategory::System],
            [NotificationCategory::Admin],
        ];
    }

    #[DataProvider('mandatoryCategories')]
    public function test_mandatory_categories_are_delivered_when_disabled(
        NotificationCategory $category,
    ): void {
        $this->user->update(['notification_prefs' => ['enabled' => false]]);

        $this->makeDevice($this->user, 'device-token-2');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('send')->once()->andReturn(FcmService::SENT);
        });

        app(NotificationService::class)->notifyRaw(
            $this->user->fresh(),
            $category,
            'Muhim xabar',
            'Matn',
        );
    }

    /**
     * @return array<int,array{0:NotificationCategory}>
     */
    public static function optionalCategories(): array
    {
        return [
            [NotificationCategory::DailyGreeting],
            [NotificationCategory::OrderReminder],
        ];
    }

    #[DataProvider('optionalCategories')]
    public function test_optional_categories_stop_when_disabled(
        NotificationCategory $category,
    ): void {
        $this->user->update(['notification_prefs' => ['enabled' => false]]);

        $this->makeDevice($this->user, 'device-token-3');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('send');
        });

        app(NotificationService::class)->notifyRaw(
            $this->user->fresh(),
            $category,
            'Eslatma',
            'Matn',
        );
    }

    public function test_invalid_push_token_is_cleared(): void
    {
        $device = $this->makeDevice($this->user, 'dead-token');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('send')->once()->andReturn(FcmService::INVALID);
        });

        app(NotificationService::class)->notifyRaw(
            $this->user,
            NotificationCategory::Admin,
            'Sarlavha',
            'Matn',
        );

        $this->assertDatabaseHas('user_devices', [
            'id' => $device->id,
            'push_token' => null,
        ]);
    }

    public function test_duplicate_push_token_sends_once(): void
    {
        $this->makeDevice($this->user, 'same-token');
        $this->makeDevice($this->user, 'same-token');

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('send')->once()->andReturn(FcmService::SENT);
        });

        app(NotificationService::class)->notifyRaw(
            $this->user,
            NotificationCategory::Admin,
            'Sarlavha',
            'Matn',
        );
    }

    // ─── Ommaviy yuborish (admin panel) ──────────────────────────────────

    public function test_broadcast_reaches_all_active_users_but_not_blocked(): void
    {
        $active = User::create([
            'name' => 'Faol',
            'phone' => '+998907778899',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        $blocked = User::create([
            'name' => 'Bloklangan',
            'phone' => '+998907778800',
            'password' => 'secret123',
            'is_accepted_policy' => true,
            'blocked_at' => now(),
        ]);

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        (new SendBulkNotification(null, 'Umumiy', 'Hammaga'))
            ->handle(app(NotificationService::class));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->user->id, 'title' => 'Umumiy']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $active->id, 'title' => 'Umumiy']);
        // Bloklangan foydalanuvchi xabar olmasligi kerak.
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $blocked->id]);
    }

    public function test_targeted_send_reaches_only_selected_users(): void
    {
        $other = User::create([
            'name' => 'Tanlanmagan',
            'phone' => '+998906665544',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        $this->mock(FcmService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        (new SendBulkNotification([$this->user->id], 'Shaxsiy', 'Faqat sizga'))
            ->handle(app(NotificationService::class));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->user->id, 'title' => 'Shaxsiy']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $other->id]);
    }

    // ─── Push token ro'yxatga olish ──────────────────────────────────────

    public function test_push_token_is_bound_to_current_device(): void
    {
        $token = $this->user->createToken('mobile');

        UserDevice::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->getKey(),
            'platform' => 'ios',
        ]);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/notifications/push-token', [
                'push_token' => 'fcm-token-abc',
                'platform' => 'ios',
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_devices', [
            'token_id' => $token->accessToken->getKey(),
            'push_token' => 'fcm-token-abc',
        ]);
    }

    public function test_push_token_moves_to_new_device_on_reuse(): void
    {
        $old = User::create([
            'name' => 'Eski',
            'phone' => '+998905556677',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        $oldDevice = $this->makeDevice($old, 'shared-device-token');

        $token = $this->user->createToken('mobile');
        UserDevice::create([
            'user_id' => $this->user->id,
            'token_id' => $token->accessToken->getKey(),
        ]);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/notifications/push-token', ['push_token' => 'shared-device-token'])
            ->assertOk();

        // Eski egasidan uzilishi shart — aks holda push noto'g'ri odamga borardi.
        $this->assertDatabaseHas('user_devices', ['id' => $oldDevice->id, 'push_token' => null]);
        $this->assertDatabaseHas('user_devices', [
            'token_id' => $token->accessToken->getKey(),
            'push_token' => 'shared-device-token',
        ]);
    }
}
