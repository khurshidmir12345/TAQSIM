<?php

namespace App\Services;

use App\Models\SystemBot;
use Illuminate\Support\Facades\Log;

/**
 * Ro'yxatdan o'tish urinishlari (telefon + kod) haqida admin Telegram
 * guruhiga xabar yuboradi. Aktiv 'notify' turidagi botdan foydalanadi.
 * Har qanday xatolik yutib yuboriladi — asosiy auth oqimini buzmaydi.
 */
class RegistrationNotifier
{
    public function __construct(
        private readonly TelegramBotService $telegram,
    ) {}

    public function notifyOtpRequested(string $phone, string $code, bool $phoneExists): void
    {
        try {
            $bot = SystemBot::query()
                ->where('type', 'notify')
                ->where('is_active', true)
                ->whereNotNull('chat_id')
                ->latest()
                ->first();

            if (! $bot) {
                return;
            }

            $status = $phoneExists ? "\u{1F501} Mavjud foydalanuvchi" : "\u{1F195} Yangi ro'yxatdan o'tish";

            $text = "\u{1F4F2} <b>TAQSEEM — kirish kodi</b>\n\n"
                . "{$status}\n"
                . "\u{260E}\u{FE0F} Telefon: <code>{$phone}</code>\n"
                . "\u{1F511} Kod: <code>{$code}</code>\n"
                . "\u{1F551} " . now()->format('d.m.Y H:i');

            $this->telegram->sendMessage($bot->token, (int) $bot->chat_id, $text);
        } catch (\Throwable $e) {
            Log::warning('RegistrationNotifier failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
