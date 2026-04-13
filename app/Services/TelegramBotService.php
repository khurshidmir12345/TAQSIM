<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    private const API_BASE = 'https://api.telegram.org/bot';

    public function sendMessage(string $token, int $chatId, string $text, ?array $replyMarkup = null): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        $response = Http::post(self::API_BASE . $token . '/sendMessage', $payload);

        if (! $response->successful()) {
            Log::error('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'response' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function setWebhook(string $token, string $url): array
    {
        $response = Http::post(self::API_BASE . $token . '/setWebhook', [
            'url' => $url,
        ]);

        return $response->json() ?? [];
    }

    public function requestContact(string $token, int $chatId, string $text): bool
    {
        return $this->sendMessage($token, $chatId, $text, [
            'keyboard' => [
                [
                    ['text' => "\u{1F4F1} Telefon raqamni yuborish", 'request_contact' => true],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    public function sendWithInlineButton(string $token, int $chatId, string $text, string $buttonText, string $url): bool
    {
        return $this->sendMessage($token, $chatId, $text, [
            'inline_keyboard' => [
                [
                    ['text' => $buttonText, 'url' => $url],
                ],
            ],
        ]);
    }

    public function removeKeyboard(string $token, int $chatId, string $text): bool
    {
        return $this->sendMessage($token, $chatId, $text, [
            'remove_keyboard' => true,
        ]);
    }
}
