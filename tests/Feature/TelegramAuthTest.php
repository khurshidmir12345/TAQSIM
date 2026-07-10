<?php

namespace Tests\Feature;

use App\Models\SystemBot;
use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery\MockInterface;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeBot(): SystemBot
    {
        return SystemBot::create([
            'name' => 'Test Bot',
            'type' => 'register',
            'username' => 'taqseem_test_bot',
            'token' => 'test-bot-token-123',
            'is_active' => true,
        ]);
    }

    /**
     * TelegramBotService'ni soxta (fake) qilib bog'laydi — Telegram API'ga
     * haqiqiy tarmoq so'rovi yubormasdan webhook mantiqini sinaymiz.
     */
    private function fakeTelegramService(): MockInterface
    {
        return $this->mock(TelegramBotService::class, function (MockInterface $mock) {
            $mock->shouldReceive('requestContact')->andReturn(true);
        });
    }

    private function postWebhookContact(string $botToken, int $chatId, int $fromId, ?int $contactUserId, string $phone = '998901234567'): TestResponse
    {
        return $this->postJson("/api/telegram/webhook/{$botToken}", [
            'message' => [
                'chat' => ['id' => $chatId],
                'from' => [
                    'id' => $fromId,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'contact' => array_filter([
                    'phone_number' => $phone,
                    'first_name' => 'Test',
                    'user_id' => $contactUserId,
                ], fn ($v) => $v !== null),
            ],
        ]);
    }

    // ── createSession: client_platform ──────────────────────────────────

    public function test_create_session_defaults_to_mobile_platform(): void
    {
        $this->makeBot();

        $response = $this->postJson('/api/v1/auth/telegram/session', []);

        $response->assertOk()
            ->assertJsonPath('data.client_platform', 'mobile');

        $this->assertDatabaseHas('telegram_auth_sessions', [
            'session_token' => $response->json('data.session_token'),
            'client_platform' => 'mobile',
        ]);
    }

    public function test_create_session_accepts_web_platform(): void
    {
        $this->makeBot();

        $response = $this->postJson('/api/v1/auth/telegram/session', [
            'client_platform' => 'web',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.client_platform', 'web');

        $this->assertDatabaseHas('telegram_auth_sessions', [
            'session_token' => $response->json('data.session_token'),
            'client_platform' => 'web',
        ]);
    }

    public function test_create_session_rejects_invalid_platform(): void
    {
        $this->makeBot();

        $response = $this->postJson('/api/v1/auth/telegram/session', [
            'client_platform' => 'desktop',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_platform']);
    }

    // ── Webhook contact: xavfsizlik ─────────────────────────────────────

    public function test_contact_from_another_telegram_user_is_rejected(): void
    {
        $bot = $this->makeBot();
        $this->fakeTelegramService()->shouldReceive('sendMessage')->once()->andReturn(true);

        $chatId = 555000111;
        TelegramAuthSession::create([
            'session_token' => 'sess-1',
            'type' => 'login',
            'client_platform' => 'mobile',
            'telegram_chat_id' => $chatId,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        // contact.user_id (999) != from.id (111) — begona kontakt.
        $response = $this->postWebhookContact($bot->token, $chatId, fromId: 111, contactUserId: 999);

        $response->assertOk();

        $this->assertSame(0, User::count());
        $this->assertDatabaseHas('telegram_auth_sessions', [
            'session_token' => 'sess-1',
            'status' => 'pending',
            'user_id' => null,
            'auth_token' => null,
        ]);
    }

    public function test_contact_without_pending_session_does_not_create_user_or_token(): void
    {
        $bot = $this->makeBot();
        $this->fakeTelegramService()->shouldReceive('sendMessage')->once()->andReturn(true);

        // Hech qanday /start qilinmagan, session mavjud emas.
        $response = $this->postWebhookContact($bot->token, chatId: 555000222, fromId: 222, contactUserId: 222);

        $response->assertOk();

        $this->assertSame(0, User::count());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_contact_completes_mobile_login_and_returns_app_redirect_url(): void
    {
        $bot = $this->makeBot();
        $this->fakeTelegramService()
            ->shouldReceive('sendWithInlineButton')
            ->once()
            ->withArgs(function (string $token, int $chatId, string $text, string $buttonText, string $url) {
                return str_contains($url, '/auth/app-redirect')
                    && str_contains($url, 'session=sess-mobile');
            })
            ->andReturn(true);

        $chatId = 555000333;
        $session = TelegramAuthSession::create([
            'session_token' => 'sess-mobile',
            'type' => 'login',
            'client_platform' => 'mobile',
            'telegram_chat_id' => $chatId,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postWebhookContact($bot->token, $chatId, fromId: 333, contactUserId: 333);

        $response->assertOk();

        $this->assertSame(1, User::count());
        $user = User::first();
        $this->assertSame('+998901234567', $user->phone);

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertNotNull($session->auth_token);
        $this->assertSame($user->id, $session->user_id);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'platform' => 'telegram',
        ]);
    }

    public function test_contact_completes_web_login_and_returns_web_app_url(): void
    {
        $bot = $this->makeBot();
        $this->fakeTelegramService()
            ->shouldReceive('sendWithInlineButton')
            ->once()
            ->withArgs(function (string $token, int $chatId, string $text, string $buttonText, string $url) {
                return $url === rtrim(config('services.telegram.web_app_url'), '/').'/telegram';
            })
            ->andReturn(true);

        $chatId = 555000444;
        TelegramAuthSession::create([
            'session_token' => 'sess-web',
            'type' => 'login',
            'client_platform' => 'web',
            'telegram_chat_id' => $chatId,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postWebhookContact($bot->token, $chatId, fromId: 444, contactUserId: 444);

        $response->assertOk();

        $this->assertSame(1, User::count());
        $user = User::first();

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'platform' => 'web',
        ]);
    }

    public function test_duplicate_contact_completion_does_not_issue_second_token(): void
    {
        $bot = $this->makeBot();
        $this->mock(TelegramBotService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendWithInlineButton')->once()->andReturn(true);
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $chatId = 555000555;
        TelegramAuthSession::create([
            'session_token' => 'sess-dup',
            'type' => 'login',
            'client_platform' => 'mobile',
            'telegram_chat_id' => $chatId,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        // Birinchi contact — muvaffaqiyatli tugaydi.
        $this->postWebhookContact($bot->token, $chatId, fromId: 555, contactUserId: 555)->assertOk();

        // Ikkinchisi (masalan Telegram qayta yetkazgan) — sessiya endi
        // "pending" emas, shuning uchun yangi token yaratilmasligi kerak.
        $this->postWebhookContact($bot->token, $chatId, fromId: 555, contactUserId: 555)->assertOk();

        $this->assertSame(1, User::count());
        $this->assertSame(1, PersonalAccessToken::count());
        $this->assertSame(1, UserDevice::count());
    }
}
