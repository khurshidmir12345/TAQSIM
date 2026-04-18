<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemBot;
use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $telegram,
    ) {}

    public function handle(Request $request, string $botToken): JsonResponse
    {
        $bot = SystemBot::where('token', $botToken)
            ->where('is_active', true)
            ->first();

        if (! $bot) {
            return response()->json(['ok' => false], 404);
        }

        $update = $request->all();

        if (isset($update['message'])) {
            $this->processMessage($bot, $update['message']);
        }

        return response()->json(['ok' => true]);
    }

    private function processMessage(SystemBot $bot, array $message): void
    {
        $chatId = $message['chat']['id'];

        if (isset($message['contact'])) {
            $this->handleContact($bot, $chatId, $message['contact'], $message);

            return;
        }

        $text = $message['text'] ?? '';

        if (str_starts_with($text, '/start')) {
            $this->handleStart($bot, $chatId, $text, $message);
        }
    }

    private function handleStart(SystemBot $bot, int $chatId, string $text, array $message): void
    {
        $parts = explode(' ', $text, 2);
        $sessionToken = $parts[1] ?? null;

        if ($sessionToken) {
            TelegramAuthSession::where('session_token', $sessionToken)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->update(['telegram_chat_id' => $chatId]);
        }

        $firstName = $message['from']['first_name'] ?? 'Foydalanuvchi';

        $this->telegram->requestContact(
            $bot->token,
            $chatId,
            "Assalomu alaykum, {$firstName}! \u{1F44B}\n\n"
            . "\u{1F4F2} <b>TAQSEEM</b> ilovasiga kirish uchun telefon raqamingizni yuboring.\n\n"
            . "Pastdagi tugmani bosing \u{1F447}",
        );
    }

    private function handleContact(SystemBot $bot, int $chatId, array $contact, array $message): void
    {
        $phone = $contact['phone_number'];
        $firstName = $contact['first_name'] ?? $message['from']['first_name'] ?? null;
        $telegramUsername = $message['from']['username'] ?? null;

        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        $user = User::where('phone', $phone)->first()
            ?? User::where('telegram_chat_id', $chatId)->first();

        if (! $user) {
            $user = User::create([
                'name' => $firstName,
                'phone' => $phone,
                'telegram_chat_id' => $chatId,
                'telegram_username' => $telegramUsername,
                'is_accepted_policy' => true,
                'phone_verified_at' => now(),
            ]);
        } else {
            $user->update([
                'telegram_chat_id' => $chatId,
                'telegram_username' => $telegramUsername,
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        $session = TelegramAuthSession::where('telegram_chat_id', $chatId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($session) {
            $session->update([
                'phone' => $phone,
                'first_name' => $firstName,
                'user_id' => $user->id,
                'auth_token' => $token,
                'status' => 'completed',
            ]);
        }

        $appRedirectUrl = config('app.url') . '/auth/app-redirect';
        if ($session) {
            $appRedirectUrl .= '?session=' . urlencode($session->session_token);
        }

        $this->telegram->sendWithInlineButton(
            $bot->token,
            $chatId,
            "\u{2705} <b>Muvaffaqiyatli!</b>\n\n"
            . "Siz tizimga kirdingiz. Endi ilovaga qaytishingiz mumkin.\n\n"
            . "Pastdagi tugmani bosing \u{1F447}",
            "\u{1F4F2} Ilovaga qaytish",
            $appRedirectUrl,
        );
    }
}
