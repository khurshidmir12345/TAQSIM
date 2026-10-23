<?php

namespace Tests\Feature;

use App\Models\BotChat;
use App\Models\SupportThread;
use App\Models\SystemBot;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class SupportRelayTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'support-bot-token';

    private const SUPPORT_GROUP = -1001111111111;

    private const USER_CHAT = 555000111;

    private const ADMIN_ID = 42;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeBot(string $type = 'notify'): SystemBot
    {
        return SystemBot::create([
            'name' => 'Support Bot',
            'type' => $type,
            'username' => 'taqseem_admin_bot',
            'token' => self::TOKEN,
            'is_active' => true,
        ]);
    }

    private function makeSupportGroup(SystemBot $bot): BotChat
    {
        return BotChat::create([
            'system_bot_id' => $bot->id,
            'chat_id' => (string) self::SUPPORT_GROUP,
            'title' => 'Savol-javob',
            'purpose' => BotChat::PURPOSE_SUPPORT,
            'is_active' => true,
        ]);
    }

    /** Foydalanuvchidan kelgan shaxsiy xabar. */
    private function userMessage(string $text = 'Salom, savolim bor'): array
    {
        return [
            'message' => [
                'message_id' => 7001,
                'from' => ['id' => self::USER_CHAT, 'first_name' => 'Ali', 'username' => 'ali_dev'],
                'chat' => ['id' => self::USER_CHAT, 'type' => 'private'],
                'text' => $text,
            ],
        ];
    }

    /** Guruhda forward xabarga qilingan reply. */
    private function groupReply(int $fromId, int $replyToMessageId, string $text = 'Javob matni'): array
    {
        return [
            'message' => [
                'message_id' => 9001,
                'from' => ['id' => $fromId, 'first_name' => 'Admin'],
                'chat' => ['id' => self::SUPPORT_GROUP, 'type' => 'supergroup'],
                'text' => $text,
                'reply_to_message' => ['message_id' => $replyToMessageId],
            ],
        ];
    }

    public function test_user_message_is_forwarded_to_support_group(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forwardMessage')
                ->once()
                ->with(self::TOKEN, self::SUPPORT_GROUP, self::USER_CHAT, 7001)
                ->andReturn(8001);
            // Suhbat boshida foydalanuvchiga tasdiq yuboriladi.
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->userMessage())
            ->assertOk();

        $this->assertDatabaseHas('support_threads', [
            'group_chat_id' => (string) self::SUPPORT_GROUP,
            'group_message_id' => 8001,
            'user_chat_id' => (string) self::USER_CHAT,
            'user_username' => 'ali_dev',
        ]);
    }

    public function test_admin_reply_reaches_the_user(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);

        SupportThread::create([
            'system_bot_id' => $bot->id,
            'group_chat_id' => (string) self::SUPPORT_GROUP,
            'group_message_id' => 8001,
            'user_chat_id' => (string) self::USER_CHAT,
        ]);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getChatAdministrators')
                ->andReturn([self::ADMIN_ID]);
            // Javob foydalanuvchiga ketadi.
            $mock->shouldReceive('sendMessage')
                ->once()
                ->withArgs(fn (string $token, int $chatId, string $text): bool => $chatId === self::USER_CHAT
                    && str_contains($text, 'Javob matni'))
                ->andReturn(true);
            // Guruhda "yuborildi" tasdig'i.
            $mock->shouldReceive('replyToMessage')->once()->andReturn(true);
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->groupReply(self::ADMIN_ID, 8001))
            ->assertOk();
    }

    /** Admin guruhda ovoz/video/rasm bilan reply qildi (matnsiz). */
    private function groupMediaReply(array $media): array
    {
        return [
            'message' => array_merge([
                'message_id' => 9002,
                'from' => ['id' => self::ADMIN_ID, 'first_name' => 'Admin'],
                'chat' => ['id' => self::SUPPORT_GROUP, 'type' => 'supergroup'],
                'reply_to_message' => ['message_id' => 8001],
            ], $media),
        ];
    }

    private function makeThread(SystemBot $bot): void
    {
        SupportThread::create([
            'system_bot_id' => $bot->id,
            'group_chat_id' => (string) self::SUPPORT_GROUP,
            'group_message_id' => 8001,
            'user_chat_id' => (string) self::USER_CHAT,
        ]);
    }

    public function test_admin_voice_reply_is_copied_to_user_with_header(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);
        $this->makeThread($bot);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getChatAdministrators')->andReturn([self::ADMIN_ID]);
            $mock->shouldReceive('copyMessage')
                ->once()
                ->withArgs(fn (string $token, int $to, int $from, int $id, ?string $caption): bool => $to === self::USER_CHAT
                    && $from === self::SUPPORT_GROUP
                    && $id === 9002
                    && str_contains((string) $caption, "Qo'llab-quvvatlash xizmati"))
                ->andReturn(true);
            $mock->shouldNotReceive('sendMessage');
            $mock->shouldReceive('replyToMessage')
                ->once()
                ->withArgs(fn ($t, $c, $r, string $text): bool => str_contains($text, 'yuborildi'))
                ->andReturn(true);
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->groupMediaReply([
            'voice' => ['file_id' => 'voice-1', 'duration' => 4],
        ]))->assertOk();
    }

    public function test_admin_photo_caption_is_kept_and_escaped(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);
        $this->makeThread($bot);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getChatAdministrators')->andReturn([self::ADMIN_ID]);
            $mock->shouldReceive('copyMessage')
                ->once()
                ->withArgs(fn ($t, $to, $from, $id, ?string $caption): bool => str_contains((string) $caption, 'Shu yerni bosing &lt;3'))
                ->andReturn(true);
            $mock->shouldReceive('replyToMessage')->once()->andReturn(true);
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->groupMediaReply([
            'photo' => [['file_id' => 'p1', 'width' => 90, 'height' => 90]],
            'caption' => 'Shu yerni bosing <3',
        ]))->assertOk();
    }

    public function test_admin_video_note_gets_header_then_copy(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);
        $this->makeThread($bot);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getChatAdministrators')->andReturn([self::ADMIN_ID]);
            // Dumaloq videoga izoh qo'yib bo'lmaydi — sarlavha alohida.
            $mock->shouldReceive('sendMessage')
                ->once()
                ->withArgs(fn ($t, int $chatId, string $text): bool => $chatId === self::USER_CHAT
                    && str_contains($text, "Qo'llab-quvvatlash xizmati"))
                ->andReturn(true);
            $mock->shouldReceive('copyMessage')
                ->once()
                ->withArgs(fn ($t, $to, $from, $id, $caption = null): bool => $to === self::USER_CHAT && $caption === null)
                ->andReturn(true);
            $mock->shouldReceive('replyToMessage')->once()->andReturn(true);
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->groupMediaReply([
            'video_note' => ['file_id' => 'vn-1', 'length' => 240, 'duration' => 5],
        ]))->assertOk();
    }

    public function test_non_admin_reply_is_ignored(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);

        SupportThread::create([
            'system_bot_id' => $bot->id,
            'group_chat_id' => (string) self::SUPPORT_GROUP,
            'group_message_id' => 8001,
            'user_chat_id' => (string) self::USER_CHAT,
        ]);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getChatAdministrators')->andReturn([self::ADMIN_ID]);
            $mock->shouldNotReceive('sendMessage');
            $mock->shouldNotReceive('replyToMessage');
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->groupReply(999, 8001))
            ->assertOk();
    }

    public function test_reply_to_unrelated_message_is_ignored(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('sendMessage');
            $mock->shouldNotReceive('getChatAdministrators');
        });

        // 8001 uchun hech qanday thread yo'q — oddiy guruh suhbati.
        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->groupReply(self::ADMIN_ID, 8001))
            ->assertOk();
    }

    public function test_message_is_not_relayed_when_support_group_missing(): void
    {
        $this->makeBot();

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forwardMessage');
        });

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, $this->userMessage())
            ->assertOk();

        $this->assertDatabaseCount('support_threads', 0);
    }

    public function test_bot_added_to_group_is_registered(): void
    {
        $bot = $this->makeBot();

        $this->mock(TelegramBotService::class, fn (MockInterface $mock) => $mock);

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, [
            'my_chat_member' => [
                'chat' => ['id' => -1002222222222, 'type' => 'supergroup', 'title' => 'Yangi guruh'],
                'new_chat_member' => ['status' => 'member'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('bot_chats', [
            'system_bot_id' => $bot->id,
            'chat_id' => '-1002222222222',
            'title' => 'Yangi guruh',
            'is_active' => true,
            'purpose' => null,
        ]);
    }

    public function test_bot_removed_from_group_is_deactivated(): void
    {
        $bot = $this->makeBot();
        $this->makeSupportGroup($bot);

        $this->mock(TelegramBotService::class, fn (MockInterface $mock) => $mock);

        $this->postJson('/api/telegram/webhook/'.self::TOKEN, [
            'my_chat_member' => [
                'chat' => ['id' => self::SUPPORT_GROUP, 'type' => 'supergroup', 'title' => 'Savol-javob'],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('bot_chats', [
            'chat_id' => (string) self::SUPPORT_GROUP,
            'is_active' => false,
        ]);
    }

    /** Regressiya: ro'yxatdan o'tish boti support oqimiga tushib qolmasin. */
    public function test_register_bot_private_message_is_not_relayed(): void
    {
        $bot = SystemBot::create([
            'name' => 'Register Bot',
            'type' => 'register',
            'username' => 't_register_bot',
            'token' => 'register-token',
            'is_active' => true,
        ]);

        BotChat::create([
            'system_bot_id' => $bot->id,
            'chat_id' => (string) self::SUPPORT_GROUP,
            'purpose' => BotChat::PURPOSE_SUPPORT,
            'is_active' => true,
        ]);

        $this->mock(TelegramBotService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('forwardMessage');
        });

        $this->postJson('/api/telegram/webhook/register-token', $this->userMessage())
            ->assertOk();

        $this->assertDatabaseCount('support_threads', 0);
    }
}
