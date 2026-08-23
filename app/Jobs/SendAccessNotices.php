<?php

namespace App\Jobs;

use App\Models\AccessNotice;
use App\Models\SystemBot;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Muddat tugashi haqida Telegram orqali ogohlantiradi.
 *
 * Nega aynan Telegram: ilova ichida narx, to'lov yoki tarif haqida gapirib
 * bo'lmaydi (App Store / Play Store qoidalari). Ilovadan tashqarida —
 * botda — buni aytish ruxsat etilgan. Shuning uchun ilovada Telegramni
 * ulashni so'raydigan modal bor.
 *
 * Faqat **egalarga** yuboriladi: xodim tarifni hal qilmaydi, uning kirishi
 * egasining muddatiga bog'liq.
 */
class SendAccessNotices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Bir martada shuncha foydalanuvchi yuklanadi. */
    private const CHUNK = 200;

    public int $timeout = 1800;

    public int $tries = 1;

    public function handle(TelegramBotService $telegram): void
    {
        if (! config('access.enabled')) {
            return;
        }

        $bot = SystemBot::query()
            ->where('type', 'register')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $bot) {
            Log::warning('[access] Faol register bot topilmadi — ogohlantirish yuborilmadi.');

            return;
        }

        $sent = 0;

        foreach ((array) config('access.notice_days', []) as $daysBefore) {
            $sent += $this->sendForDay($telegram, $bot->token, (int) $daysBefore);
        }

        Log::info('[access] Ogohlantirishlar yuborildi', ['count' => $sent]);
    }

    /**
     * Muddati aynan shuncha kundan keyin tugaydiganlarga xabar yuboradi.
     */
    private function sendForDay(TelegramBotService $telegram, string $token, int $daysBefore): int
    {
        $target = now()->addDays($daysBefore);
        $sent = 0;

        User::query()
            ->whereNull('blocked_at')
            ->whereNotNull('telegram_chat_id')
            ->whereBetween('access_until', [$target->copy()->startOfDay(), $target->copy()->endOfDay()])
            // Faqat egalar: xodimning kirishi egasining muddatiga bog'liq.
            ->whereHas('userShops', fn ($q) => $q->where('user_type', 'owner'))
            ->chunkById(self::CHUNK, function (Collection $users) use ($telegram, $token, $daysBefore, &$sent): void {
                foreach ($users as $user) {
                    if ($this->send($telegram, $token, $user, $daysBefore)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function send(TelegramBotService $telegram, string $token, User $user, int $daysBefore): bool
    {
        // Yozuvni oldin qo'yamiz: unique kalit ikkinchi urinishni to'xtatadi,
        // shuning uchun job qayta ishga tushsa ham xabar takrorlanmaydi.
        try {
            AccessNotice::create([
                'user_id' => $user->id,
                'access_until' => $user->access_until,
                'days_before' => $daysBefore,
                'sent_at' => now(),
            ]);
        } catch (QueryException) {
            return false; // Allaqachon yuborilgan.
        }

        $key = $daysBefore === 0 ? 'access.notice.ended' : 'access.notice.ending';

        $text = __($key, [
            'days' => (string) $daysBefore,
            'contact' => (string) config('access.contact'),
        ], $user->locale ?: config('app.locale'));

        try {
            $telegram->sendMessage($token, (int) $user->telegram_chat_id, $text);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[access] Telegram xabari yuborilmadi', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
