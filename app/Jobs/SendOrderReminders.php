<?php

namespace App\Jobs;

use App\Enums\CustomerOrderStatus;
use App\Enums\NotificationCategory;
use App\Models\CustomerOrder;
use App\Models\Shop;
use App\Models\SystemBot;
use App\Services\NotificationService;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * "Bugun zakaz bor" eslatmasi — yetkazish sanasi bugunga to'g'ri keladigan
 * aktiv zakazlari bor do'konlarning barcha xodimlariga: push va, Telegram
 * ulagan bo'lsa, bot orqali ham (kuch-quvvat tilagi bilan).
 *
 * Erta tongda ishlaydi (`notifications:order-reminder`), shuning uchun
 * "bugun" mahalliy vaqt bo'yicha hisoblanadi — UTC'da hali kecha bo'lishi
 * mumkin.
 */
class SendOrderReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Bir martada shuncha do'kon yuklanadi. */
    private const CHUNK = 100;

    public int $timeout = 1800;

    public int $tries = 1;

    public function handle(NotificationService $notifications): void
    {
        $today = now(config('app.business_timezone'))->toDateString();

        // Do'kon bo'yicha bugungi aktiv zakazlar soni — bitta so'rov.
        $counts = CustomerOrder::query()
            ->where('status', CustomerOrderStatus::Active->value)
            ->whereDate('delivery_date', $today)
            ->groupBy('shop_id')
            ->selectRaw('shop_id, COUNT(*) as total')
            ->pluck('total', 'shop_id');

        if ($counts->isEmpty()) {
            Log::info('Zakaz eslatmasi: bugunga zakaz yo\'q', ['sana' => $today]);

            return;
        }

        $sent = 0;
        $skipped = 0;
        $telegramSent = 0;

        $telegram = app(TelegramBotService::class);
        $botToken = SystemBot::query()
            ->where('type', 'register')
            ->where('is_active', true)
            ->latest()
            ->value('token');

        Shop::query()
            ->whereIn('id', $counts->keys()->all())
            ->with(['users' => fn ($q) => $q->whereNull('blocked_at')])
            ->chunkById(self::CHUNK, function (Collection $shops) use ($notifications, $telegram, $botToken, $counts, &$sent, &$skipped, &$telegramSent): void {
                foreach ($shops as $shop) {
                    $count = (int) $counts->get($shop->id, 0);

                    if ($count < 1) {
                        continue;
                    }

                    foreach ($shop->users as $user) {
                        // Telegram — push sozlamasidan mustaqil: bot ulangan bo'lsa ketadi.
                        if ($botToken !== null && $user->telegram_chat_id) {
                            $text = __('notification.order_reminder.telegram', [
                                'shop' => (string) $shop->name,
                                'count' => (string) $count,
                            ], $user->messageLocale());
                            try {
                                if ($telegram->sendMessage($botToken, (int) $user->telegram_chat_id, $text)) {
                                    $telegramSent++;
                                }
                            } catch (\Throwable $e) {
                                Log::warning('Zakaz eslatmasi (Telegram) yuborilmadi', [
                                    'user_id' => $user->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        if (! $notifications->wantsPush($user, NotificationCategory::OrderReminder)) {
                            $skipped++;

                            continue;
                        }

                        try {
                            $notifications->notify(
                                $user,
                                NotificationCategory::OrderReminder,
                                'notification.order_reminder.title',
                                'notification.order_reminder.body',
                                ['shop' => (string) $shop->name, 'count' => (string) $count],
                                ['type' => 'order_reminder', 'shop_id' => (string) $shop->id],
                            );

                            $sent++;
                        } catch (\Throwable $e) {
                            // Bitta foydalanuvchidagi xato qolganlarni to'xtatmasin.
                            Log::warning('Zakaz eslatmasi yuborilmadi', [
                                'user_id' => $user->id,
                                'shop_id' => $shop->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

        Log::info('Zakaz eslatmasi yakunlandi', [
            'sana' => $today,
            'dokonlar' => $counts->count(),
            'yuborildi' => $sent,
            'telegram' => $telegramSent,
            'ochirgan' => $skipped,
        ]);
    }
}
