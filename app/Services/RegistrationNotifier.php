<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\SystemBot;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Ro'yxatdan o'tish va xodim taklifi urinishlari (telefon + kod) haqida admin
 * Telegram guruhiga xabar yuboradi. Aktiv 'notify' turidagi botdan foydalanadi.
 * Har qanday xatolik yutib yuboriladi — asosiy oqimni buzmaydi.
 */
class RegistrationNotifier
{
    public function __construct(
        private readonly TelegramBotService $telegram,
    ) {}

    public function notifyOtpRequested(string $phone, string $code, bool $phoneExists): void
    {
        try {
            $bot = $this->notifyBot();

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

    /**
     * Xodim taklifi: kim, qaysi biznesga, kimni qo'shmoqchi va yuborilgan kod.
     */
    public function notifyEmployeeInvite(
        User $owner,
        Shop $shop,
        string $employeeName,
        string $employeePhone,
        string $code,
    ): void {
        try {
            $bot = $this->notifyBot();

            if (! $bot) {
                return;
            }

            $ownerName = $this->esc($owner->name ?: '—');
            $ownerPhone = $this->esc($owner->phone ?: '—');
            $shopName = $this->esc($shop->name ?: '—');
            $employee = $this->esc($employeeName);
            $phone = $this->esc($employeePhone);

            $text = "\u{1F477} <b>TAQSEEM — xodim qo'shish</b>\n\n"
                . "\u{1F464} Kim qo'shmoqchi: <b>{$ownerName}</b> (<code>{$ownerPhone}</code>)\n"
                . "\u{1F3E2} Biznes: <b>{$shopName}</b>\n\n"
                . "\u{2795} Xodim: <b>{$employee}</b>\n"
                . "\u{260E}\u{FE0F} Telefon: <code>{$phone}</code>\n"
                . "\u{1F511} Kod: <code>{$code}</code>\n"
                . "\u{1F551} " . now()->format('d.m.Y H:i');

            $this->telegram->sendMessage($bot->token, (int) $bot->chat_id, $text);
        } catch (\Throwable $e) {
            Log::warning('RegistrationNotifier employee invite failed', [
                'phone' => $employeePhone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyBot(): ?SystemBot
    {
        return SystemBot::query()
            ->where('type', 'notify')
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->latest()
            ->first();
    }

    /**
     * Xabar `parse_mode=HTML` bilan ketadi — foydalanuvchi kiritgan nomlarda
     * `<`, `>`, `&` bo'lsa Telegram xabarni rad etadi. Shuning uchun ekranlanadi.
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
