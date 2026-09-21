<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramTutorial;
use App\Models\SystemBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_sends_localized_text_with_link_to_every_telegram_user(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        SystemBot::create(['name' => 'Register', 'type' => 'register', 'token' => 'tok', 'username' => 'bot', 'is_active' => true]);

        User::factory()->create(['telegram_chat_id' => 1, 'locale' => null]);
        User::factory()->create(['telegram_chat_id' => 2, 'locale' => 'ru']);
        User::factory()->create(['telegram_chat_id' => null]);
        User::factory()->create(['telegram_chat_id' => 3, 'blocked_at' => now()]);

        $this->artisan('telegram:broadcast', ['key' => 'tutorial_video', '--link' => 'https://t.me/taqseem_rasmiy/23'])
            ->expectsOutputToContain('Qabul qiluvchilar: 2')->assertSuccessful();
        Http::assertNothingSent();

        $this->artisan('telegram:broadcast', ['key' => 'tutorial_video', '--link' => 'https://t.me/taqseem_rasmiy/23', '--send' => true])
            ->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['chat_id'] === 1 && str_contains($r['text'], 'https://t.me/taqseem_rasmiy/23') && str_contains($r['text'], 'video qo\'llanma'));
        Http::assertSent(fn ($r) => $r['chat_id'] === 2 && str_contains($r['text'], 'видеоинструкция'));

        $this->artisan('telegram:broadcast', ['key' => 'nope'])->assertFailed();
    }

    public function test_tutorial_job_sends_video_link_to_newly_linked_user(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        SystemBot::create(['name' => 'Register', 'type' => 'register', 'token' => 'tok', 'username' => 'bot', 'is_active' => true]);
        $user = User::factory()->create(['telegram_chat_id' => 77, 'locale' => null]);

        (new SendTelegramTutorial($user->id))->handle(app(\App\Services\TelegramBotService::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['chat_id'] === 77
            && str_contains($r['text'], config('access.tutorial_video_url'))
            && str_contains($r['text'], "qo'llanma"));
    }
}
