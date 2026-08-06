<?php

namespace App\Jobs;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ko'p foydalanuvchiga bildirishnoma yuboradi (admin paneldan).
 *
 * Navbatda bajariladi — har bir push alohida HTTP so'rov, shuning uchun
 * yuzlab foydalanuvchiga yuborish web so'rovini kutdirib qo'yardi.
 */
class SendBulkNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Bir martada shuncha foydalanuvchi yuklanadi. */
    private const CHUNK = 100;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * @param  array<int,string>|null  $userIds  null — barcha foydalanuvchilar
     */
    public function __construct(
        private readonly ?array $userIds,
        private readonly string $title,
        private readonly string $body,
        private readonly string $category = 'admin',
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $category = NotificationCategory::tryFrom($this->category) ?? NotificationCategory::Admin;

        $query = User::query()->whereNull('blocked_at');

        if ($this->userIds !== null) {
            $query->whereIn('id', $this->userIds);
        }

        $sent = 0;

        $query->chunkById(self::CHUNK, function ($users) use ($notifications, $category, &$sent): void {
            foreach ($users as $user) {
                try {
                    $notifications->notifyRaw($user, $category, $this->title, $this->body);
                    $sent++;
                } catch (\Throwable $e) {
                    // Bitta foydalanuvchidagi xato qolganlarni to'xtatmasin.
                    Log::warning('Bildirishnoma yuborilmadi', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Ommaviy bildirishnoma yakunlandi', [
            'jami' => $sent,
            'turi' => $category->value,
        ]);
    }
}
