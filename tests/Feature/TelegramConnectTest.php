<?php

namespace Tests\Feature;

use App\Models\SystemBot;
use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Telegramni profilga bog'lash oqimi.
 *
 * Muhim: bu oqimda telefon raqam SO'RALMAYDI — foydalanuvchi allaqachon
 * tizimga kirgan, faqat Telegram hisobi bog'lanadi.
 */
class TelegramConnectTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'register-bot-token';

    private const CHAT_ID = 555111222;

    private function makeBot(): SystemBot
    {
        return SystemBot::create([
            'name' => 'Register Bot',
            'type' => 'register',
            'username' => 't_register_bot',
            'token' => self::TOKEN,
            'is_active' => true,
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Khurshid',
            'phone' => '+998901234567',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);
    }

    private function postStart(string $payload): TestResponse
    {
        return $this->postJson('/api/telegram/webhook/'.self::TOKEN, [
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => self::CHAT_ID, 'type' => 'private'],
                'from' => [
                    'id' => self::CHAT_ID,
                    'first_name' => 'Khurshid',
                    'username' => 'khurshid_dev',
                ],
                'text' => '/start '.$payload,
            ],
        ]);
    }

    public function test_connect_links_account_without_asking_for_phone(): void
    {
        $this->makeBot();
        $user = $this->makeUser();

        $session = TelegramAuthSession::create([
            'session_token' => 'connect-token-123',
            'type' => 'connect',
            'user_id' => $user->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
            // Telefon raqam SO'RALMASLIGI kerak.
            $mock->shouldNotReceive('requestContact');
        });

        $this->postStart('connect-token-123')->assertOk();

        $user->refresh();
        $this->assertSame(self::CHAT_ID, $user->telegram_chat_id);
        $this->assertSame('khurshid_dev', $user->telegram_username);
        $this->assertSame('completed', $session->fresh()->status);
    }

    public function test_unknown_token_explains_instead_of_asking_for_phone(): void
    {
        $this->makeBot();

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            // Tushunarli xabar yuboriladi...
            $mock->shouldReceive('sendMessage')
                ->once()
                ->withArgs(fn (string $t, int $chat, string $text): bool => str_contains($text, 'eskirgan'))
                ->andReturn(true);
            // ...telefon raqam so'ralmaydi.
            $mock->shouldNotReceive('requestContact');
        });

        $this->postStart('boshqa-muhitdagi-token')->assertOk();
    }

    public function test_expired_session_is_treated_as_unknown(): void
    {
        $this->makeBot();
        $user = $this->makeUser();

        TelegramAuthSession::create([
            'session_token' => 'eskirgan',
            'type' => 'connect',
            'user_id' => $user->id,
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
            $mock->shouldNotReceive('requestContact');
        });

        $this->postStart('eskirgan')->assertOk();

        // Hisob bog'lanmagan bo'lishi kerak.
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_plain_start_without_token_still_asks_for_phone(): void
    {
        $this->makeBot();

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            // Tokensiz /start — bu oddiy login oqimi, telefon so'ralishi to'g'ri.
            $mock->shouldReceive('requestContact')->once()->andReturn(true);
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, [
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => self::CHAT_ID, 'type' => 'private'],
                'from' => ['id' => self::CHAT_ID, 'first_name' => 'Khurshid'],
                'text' => '/start',
            ],
        ])->assertOk();
    }

    public function test_connect_is_rejected_when_telegram_belongs_to_someone_else(): void
    {
        $this->makeBot();
        $owner = $this->makeUser();

        // Bu Telegram hisobi allaqachon boshqa odamga bog'langan.
        User::create([
            'name' => 'Boshqa',
            'phone' => '+998909998877',
            'password' => 'secret123',
            'is_accepted_policy' => true,
            'telegram_chat_id' => self::CHAT_ID,
        ]);

        TelegramAuthSession::create([
            'session_token' => 'connect-token-456',
            'type' => 'connect',
            'user_id' => $owner->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->withArgs(fn (string $t, int $c, string $text): bool => str_contains($text, 'bog\'langan'))
                ->andReturn(true);
        });

        $this->postStart('connect-token-456')->assertOk();

        $this->assertNull($owner->fresh()->telegram_chat_id);
    }
}
