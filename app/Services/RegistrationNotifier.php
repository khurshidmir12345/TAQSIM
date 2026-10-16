<?php

namespace App\Services;

use App\Enums\AuthProvider;
use App\Jobs\SendAdminTelegramMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Ro'yxatdan o'tish va xodim taklifi (telefon + kod) haqida admin Telegram
 * guruhiga xabar tayyorlaydi va NAVBATGA qo'yadi.
 *
 * Yuborishning o'zi [SendAdminTelegramMessage] jobida bo'ladi — shunda
 * Telegram sekinlashsa ham foydalanuvchining kirish/ro'yxatdan o'tish so'rovi
 * kutib qolmaydi. Har qanday xatolik yutiladi, asosiy oqim buzilmaydi.
 */
class RegistrationNotifier
{
    public function notifyOtpRequested(string $phone, string $code, bool $phoneExists): void
    {
        try {
            $status = $phoneExists ? "\u{1F501} Mavjud foydalanuvchi" : "\u{1F195} Yangi ro'yxatdan o'tish";

            $text = "\u{1F4F2} <b>TAQSEEM — kirish kodi</b>\n\n"
                . "{$status}\n"
                . "\u{260E}\u{FE0F} Telefon: <code>{$phone}</code>\n"
                . "\u{1F511} Kod: <code>{$code}</code>\n"
                . "\u{1F551} " . now()->format('d.m.Y H:i');

            SendAdminTelegramMessage::dispatch($text);
        } catch (\Throwable $e) {
            Log::warning('RegistrationNotifier failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Yangi foydalanuvchi yaratildi — qaysi yo'l bilan bo'lsa ham (telefon,
     * Google, Apple, Telegram). Avval faqat SMS kod so'ralgani haqida xabar
     * kelardi; Google/Apple/Telegram orqali kelganlar ko'rinmay qolardi.
     *
     * Tranzaksiya muvaffaqiyatli tugagachgina yuboriladi (`afterCommit`).
     */
    public function notifyRegistered(User $user, AuthProvider $method): void
    {
        try {
            $label = match ($method) {
                AuthProvider::Phone => "\u{1F4F1} Telefon (SMS kod)",
                AuthProvider::Google => "\u{1F310} Google",
                AuthProvider::Apple => "\u{1F34F} Apple",
                AuthProvider::Telegram => "\u{2708}\u{FE0F} Telegram",
            };

            $lines = ["\u{1F389} <b>TAQSEEM — yangi foydalanuvchi</b>", '', "Usul: <b>{$label}</b>"];
            if ($user->name) {
                $lines[] = "\u{1F464} Ism: <b>{$this->esc($user->name)}</b>";
            }
            if ($user->phone) {
                $lines[] = "\u{260E}\u{FE0F} Telefon: <code>{$this->esc($user->phone)}</code>";
            }
            if ($user->email) {
                $lines[] = "\u{2709}\u{FE0F} Email: <code>{$this->esc($user->email)}</code>";
            }
            if ($user->telegram_username) {
                $lines[] = "\u{1F4AC} Telegram: @{$this->esc($user->telegram_username)}";
            }
            $lines[] = "\u{1F465} Jami foydalanuvchilar: " . User::query()->count();
            $lines[] = "\u{1F551} " . now()->format('d.m.Y H:i');

            SendAdminTelegramMessage::dispatch(implode("\n", $lines))->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('RegistrationNotifier registered failed', [
                'user_id' => $user->id,
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

            SendAdminTelegramMessage::dispatch($text);
        } catch (\Throwable $e) {
            Log::warning('RegistrationNotifier employee invite failed', [
                'phone' => $employeePhone,
                'error' => $e->getMessage(),
            ]);
        }
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
