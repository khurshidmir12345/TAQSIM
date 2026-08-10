<?php

namespace App\Jobs;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Har kuni ertalabki tilak — biznesga bog'langan barcha foydalanuvchilarga.
 *
 * Rejalashtirilgan (`notifications:daily-greeting`), navbatda bajariladi:
 * har bir push alohida HTTP so'rov, minglab foydalanuvchida bu uzoq davom
 * etadi va scheduler jarayonini ushlab turmasligi kerak.
 */
class SendDailyGreetings implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Bir martada shuncha foydalanuvchi yuklanadi. */
    private const CHUNK = 200;

    /** Matn hafta kuni bo'yicha tanlanadi — tilak har kuni takrorlanmasin. */
    private const BODY_VARIANTS = 7;

    public int $timeout = 1800;

    public int $tries = 1;

    public function handle(NotificationService $notifications): void
    {
        $dayOfWeek = now(config('app.business_timezone'))->dayOfWeek % self::BODY_VARIANTS;
        $bodyKey = "notification.daily_greeting.bodies.{$dayOfWeek}";

        $sent = 0;
        $skipped = 0;

        // Biznesi yo'q foydalanuvchiga ish tilagi ma'nosiz — faqat do'konga
        // biriktirilganlar oladi.
        User::query()
            ->whereNull('blocked_at')
            ->whereHas('userShops')
            ->chunkById(self::CHUNK, function (Collection $users) use ($notifications, $bodyKey, &$sent, &$skipped): void {
                foreach ($users as $user) {
                    // Bildirishnomani o'chirgan foydalanuvchining ro'yxati ham
                    // har kuni to'lib ketmasin — yozuv umuman yaratilmaydi.
                    if (! $notifications->wantsPush($user, NotificationCategory::DailyGreeting)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $notifications->notify(
                            $user,
                            NotificationCategory::DailyGreeting,
                            'notification.daily_greeting.title',
                            $bodyKey,
                            [],
                            ['type' => 'daily_greeting'],
                        );

                        $sent++;
                    } catch (\Throwable $e) {
                        // Bitta foydalanuvchidagi xato qolganlarni to'xtatmasin.
                        Log::warning('Kunlik tilak yuborilmadi', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('Kunlik tilak yakunlandi', [
            'yuborildi' => $sent,
            'ochirgan' => $skipped,
        ]);
    }
}
