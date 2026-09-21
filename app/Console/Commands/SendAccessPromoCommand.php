<?php

namespace App\Console\Commands;

use App\Models\SystemBot;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Premium narxlari haqidagi bir martalik marketing xabari — Telegram bot
 * orqali, biznes egalariga (Telegram ulaganlarga).
 *
 * Avval `--dry-run` bilan nechta odamga ketishini ko'ring, so'ng haqiqiy
 * yuborish uchun `--send` bering. Ikkalasisiz hech narsa yuborilmaydi.
 */
class SendAccessPromoCommand extends Command
{
    protected $signature = 'access:promo
        {--send : Haqiqatan yuborish (aks holda faqat hisob-kitob)}
        {--expired-only : Faqat muddati tugaganlarga}
        {--locale-unset : Faqat tilini tanlamaganlarga (qayta yuborish uchun)}
        {--limit=0 : Ko\'pi bilan shuncha odamga (0 — hammaga)}';

    protected $description = 'Premium narxlari haqida Telegram orqali marketing xabari';

    public function handle(TelegramBotService $telegram): int
    {
        $bot = SystemBot::query()
            ->where('type', 'register')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $bot) {
            $this->error('Faol register bot topilmadi.');

            return self::FAILURE;
        }

        $query = User::query()
            ->whereNull('blocked_at')
            ->whereNotNull('telegram_chat_id')
            ->whereHas('userShops', fn ($q) => $q->where('user_type', 'owner'))
            ->orderBy('id');

        if ($this->option('expired-only')) {
            $query->where('access_until', '<', now());
        }

        if ($this->option('locale-unset')) {
            $query->whereNull('locale');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $users = $query->get();
        $this->info("Qabul qiluvchilar: {$users->count()}");

        if (! $this->option('send')) {
            $this->line('Dry-run — hech narsa yuborilmadi. Yuborish uchun --send bering.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            $locale = $user->messageLocale();
            $text = __('access.promo', [
                'pricing' => __('access.pricing', [], $locale),
                'contact' => (string) config('access.contact'),
            ], $locale);

            try {
                $telegram->sendMessage($bot->token, (int) $user->telegram_chat_id, $text)
                    ? $sent++
                    : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[access] Promo yuborilmadi', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Telegram chegarasi: sekundiga ~30 xabar.
            usleep(60_000);
        }

        Log::info('[access] Promo yuborildi', ['sent' => $sent, 'failed' => $failed]);
        $this->info("Yuborildi: {$sent}, xato: {$failed}");

        return self::SUCCESS;
    }
}
