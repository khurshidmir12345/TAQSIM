<?php

namespace App\Console\Commands;

use App\Models\SystemBot;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

/**
 * Telegram ulagan foydalanuvchilarga bir martalik xabar.
 *
 * Matn `lang/<til>/broadcast.php` dagi kalitdan olinadi, foydalanuvchi
 * tilida (tanlanmagan bo'lsa o'zbekcha). Avval `--send`siz soni ko'rinadi.
 *
 *   php artisan telegram:broadcast tutorial_video --link=https://t.me/... --send
 */
class TelegramBroadcastCommand extends Command
{
    protected $signature = 'telegram:broadcast
        {key : lang/broadcast.php dagi kalit}
        {--link= : Matndagi :link o\'rniga}
        {--send : Haqiqatan yuborish}
        {--owners-only : Faqat biznes egalariga}';

    protected $description = 'Telegram ulagan foydalanuvchilarga bir martalik xabar';

    public function handle(TelegramBotService $telegram): int
    {
        $key = (string) $this->argument('key');
        if (! Lang::has("broadcast.{$key}", 'uz')) {
            $this->error("lang/uz/broadcast.php da '{$key}' kaliti yo'q.");

            return self::FAILURE;
        }

        $token = SystemBot::query()
            ->where('type', 'register')
            ->where('is_active', true)
            ->latest()
            ->value('token');

        if ($token === null) {
            $this->error('Faol register bot topilmadi.');

            return self::FAILURE;
        }

        $query = User::query()
            ->whereNull('blocked_at')
            ->whereNotNull('telegram_chat_id')
            ->orderBy('id');

        if ($this->option('owners-only')) {
            $query->whereHas('userShops', fn ($q) => $q->where('user_type', 'owner'));
        }

        $users = $query->get();
        $this->info("Qabul qiluvchilar: {$users->count()}");

        if (! $this->option('send')) {
            $this->line('Dry-run — hech narsa yuborilmadi. Yuborish uchun --send bering.');

            return self::SUCCESS;
        }

        $replace = [
            'link' => (string) ($this->option('link') ?? ''),
            'contact' => (string) config('access.contact'),
        ];

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            $text = __("broadcast.{$key}", $replace, $user->messageLocale());
            try {
                $telegram->sendMessage($token, (int) $user->telegram_chat_id, $text) ? $sent++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[broadcast] Yuborilmadi', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
            usleep(60_000);
        }

        Log::info('[broadcast] Yuborildi', ['key' => $key, 'sent' => $sent, 'failed' => $failed]);
        $this->info("Yuborildi: {$sent}, xato: {$failed}");

        return self::SUCCESS;
    }
}
