<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Models\AppNotification;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Lang;

/**
 * Bildirishnoma yaratadi va (sozlamaga qarab) push yuboradi.
 *
 * Yozuv HAR DOIM yaratiladi — push sozlamasi faqat telefonda ko'rinishini
 * boshqaradi. Foydalanuvchi push'ni o'chirsa ham xabarni ilova ichidagi
 * ro'yxatda ko'radi.
 */
class NotificationService
{
    public function __construct(
        private readonly FcmService $fcm,
    ) {}

    /**
     * Tarjima kalitlari bo'yicha — matn foydalanuvchi tilida hal qilinadi.
     *
     * @param  array<string,string>  $replace  tarjimadagi :placeholder qiymatlari
     * @param  array<string,scalar>  $data     bosilganda qayerga o'tish
     */
    public function notify(
        User $user,
        NotificationCategory $category,
        string $titleKey,
        string $bodyKey,
        array $replace = [],
        array $data = [],
    ): AppNotification {
        $locale = $this->localeFor($user);

        return $this->notifyRaw(
            $user,
            $category,
            Lang::get($titleKey, $replace, $locale),
            Lang::get($bodyKey, $replace, $locale),
            $data,
        );
    }

    /**
     * Tayyor matn bilan — admin paneldan yuborilgan xabarlar uchun.
     */
    public function notifyRaw(
        User $user,
        NotificationCategory $category,
        string $title,
        string $body,
        array $data = [],
    ): AppNotification {
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);

        if ($this->wantsPush($user, $category)) {
            $this->push($user, $title, $body, $data + ['notification_id' => $notification->id]);
        }

        return $notification;
    }

    /**
     * Foydalanuvchi shu turdagi push'ni qabul qilishni xohlaydimi.
     *
     * Yagona tugma: `notification_prefs['enabled']`. O'chirilgan bo'lsa faqat
     * eslatuvchi turlar to'xtaydi — xodim qo'shilishi, tizim va admin xabari
     * baribir yetkaziladi. Kalit yo'q bo'lsa yoqiq deb hisoblanadi.
     */
    public function wantsPush(User $user, NotificationCategory $category): bool
    {
        // Majburiy turlar sozlamaga bog'liq emas.
        if (! $category->isOptional()) {
            return true;
        }

        $prefs = $user->notification_prefs;

        if (! is_array($prefs)) {
            return true;
        }

        return ($prefs['enabled'] ?? true) !== false;
    }

    /**
     * Foydalanuvchining barcha qurilmalariga yuboradi.
     * O'lik tokenlar darhol tozalanadi.
     */
    private function push(User $user, string $title, string $body, array $data): void
    {
        if (! $this->fcm->isConfigured()) {
            return;
        }

        $devices = UserDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('push_token')
            ->get();

        // Qayta o'rnatishdan keyin bir xil token bir nechta yozuvda bo'lishi
        // mumkin — bitta qurilmaga ikki marta push bormasin.
        $seen = [];

        foreach ($devices as $device) {
            $token = (string) $device->push_token;

            if ($token === '' || isset($seen[$token])) {
                continue;
            }

            $seen[$token] = true;

            $result = $this->fcm->send($token, $title, $body, $data);

            if ($result === FcmService::INVALID) {
                UserDevice::query()
                    ->where('push_token', $token)
                    ->update(['push_token' => null, 'push_token_updated_at' => now()]);
            }
        }
    }

    private function localeFor(User $user): string
    {
        $locale = $user->locale;
        $supported = ['uz', 'uz_CYRL', 'ru', 'kk', 'ky', 'tr', 'en'];

        return in_array($locale, $supported, true) ? $locale : 'uz';
    }
}
