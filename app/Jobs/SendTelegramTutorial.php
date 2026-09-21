<?php

namespace App\Jobs;

use App\Models\SystemBot;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Telegram endi ulangan foydalanuvchiga video qo'llanma havolasi.
 *
 * "Muvaffaqiyatli ulandi" xabaridan bir necha soniya keyin ketadi, shuning
 * uchun navbatda kechiktirib yuboriladi.
 */
class SendTelegramTutorial implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $userId) {}

    public function handle(TelegramBotService $telegram): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null || ! $user->telegram_chat_id || $user->isBlocked()) {
            return;
        }

        $token = SystemBot::query()
            ->where('type', 'register')
            ->where('is_active', true)
            ->latest()
            ->value('token');
        if ($token === null) {
            return;
        }

        $text = __('broadcast.tutorial_video', [
            'link' => (string) config('access.tutorial_video_url'),
            'contact' => (string) config('access.contact'),
        ], $user->messageLocale());

        try {
            $telegram->sendMessage($token, (int) $user->telegram_chat_id, $text);
        } catch (\Throwable $e) {
            Log::warning('[tutorial] Telegram qo\'llanma yuborilmadi', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
