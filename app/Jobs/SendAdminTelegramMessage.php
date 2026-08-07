<?php

namespace App\Jobs;

use App\Models\BotChat;
use App\Models\SystemBot;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Admin Telegram guruhiga xabar yuboradi — navbat orqali.
 *
 * Navbatda bo'lishi muhim: bu xabarlar ro'yxatdan o'tish va xodim qo'shish
 * oqimida yuboriladi. Ilgari so'rov ichida sinxron ketardi va Telegram
 * sekinlashsa foydalanuvchi "Ulanish vaqti tugadi" xatosini olardi.
 */
class SendAdminTelegramMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 2;

    public function __construct(
        private readonly string $text,
    ) {}

    public function handle(TelegramBotService $telegram): void
    {
        $bot = SystemBot::query()
            ->where('type', 'notify')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $bot) {
            return;
        }

        $chat = BotChat::query()
            ->where('system_bot_id', $bot->id)
            ->active()
            ->purpose(BotChat::PURPOSE_NOTIFY)
            ->latest()
            ->first();

        $chatId = $chat->chat_id ?? $bot->chat_id;

        if ($chatId === null || trim((string) $chatId) === '') {
            return;
        }

        try {
            $telegram->sendMessage($bot->token, (int) $chatId, $this->text);
        } catch (\Throwable $e) {
            Log::warning('Admin Telegram xabari yuborilmadi', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
