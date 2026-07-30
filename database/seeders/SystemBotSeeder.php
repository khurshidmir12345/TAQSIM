<?php

namespace Database\Seeders;

use App\Models\SystemBot;
use Illuminate\Database\Seeder;

class SystemBotSeeder extends Seeder
{
    /**
     * Register botni faqat mavjud bo'lmaganda yaratadi.
     *
     * Mavjud tokenni almashtirish seeder orqali emas, nazorat qilinadigan
     * admin yoki operatsion jarayon orqali bajarilishi kerak.
     */
    public function run(): void
    {
        $token = $this->normalizeToken(config('services.telegram.register_bot.token'));

        if ($token === null) {
            $this->command?->warn(
                'TELEGRAM_REGISTER_BOT_TOKEN .env da yo\'q yoki yaroqsiz — register bot seed qilinmadi (mavjud yozuv o\'zgartirilmadi).'
            );

            return;
        }

        $name = $this->normalizeName(config('services.telegram.register_bot.name'));
        $username = $this->normalizeUsername(config('services.telegram.register_bot.username'));

        $bot = SystemBot::query()->firstOrCreate(
            ['type' => 'register'],
            [
                'name' => $name,
                'username' => $username,
                'token' => $token,
                'is_active' => true,
            ]
        );

        if (! $bot->wasRecentlyCreated) {
            $this->command?->info('Register bot allaqachon mavjud — token/name/username o\'zgartirilmadi.');
        }
    }

    private function normalizeToken(mixed $token): ?string
    {
        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);

        if ($token === '' || ! str_contains($token, ':')) {
            return null;
        }

        return $token;
    }

    private function normalizeName(mixed $name): string
    {
        if (! is_string($name)) {
            return 'TAQSEEM Register Bot';
        }

        $name = trim($name);

        return $name !== '' ? $name : 'TAQSEEM Register Bot';
    }

    private function normalizeUsername(mixed $username): string
    {
        if (! is_string($username)) {
            return 't_register_bot';
        }

        $username = ltrim(trim($username), '@');

        return $username !== '' ? $username : 't_register_bot';
    }
}
