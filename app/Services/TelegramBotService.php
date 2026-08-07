<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    private const API_BASE = 'https://api.telegram.org/bot';

    /**
     * Telegram javob bermay qolsa so'rov osilib qolmasin.
     *
     * Bu chaqiruvlar auth oqimida ham ishlatiladi (ro'yxatdan o'tish kodi
     * admin guruhga yuboriladi). Laravel'ning standart 30 soniyasi mobil
     * ilovaning 15 soniyalik chegarasidan oshib ketardi va foydalanuvchi
     * "Ulanish vaqti tugadi" xatosini ko'rardi.
     */
    private const TIMEOUT = 5;

    private const CONNECT_TIMEOUT = 3;

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(self::TIMEOUT)->connectTimeout(self::CONNECT_TIMEOUT);
    }

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

        $response = $this->http()->post(self::API_BASE . $token . '/sendMessage', $payload);

        if (! $response->successful()) {
            Log::error('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'response' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Foydalanuvchi xabarini guruhga forward qiladi (matn, rasm, ovoz — hammasi).
     *
     * @return int|null guruhdagi yangi xabar ID'si; admin unga reply qiladi
     */
    public function forwardMessage(string $token, int $toChatId, int $fromChatId, int $messageId): ?int
    {
        $response = $this->http()->post(self::API_BASE.$token.'/forwardMessage', [
            'chat_id' => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ]);

        if (! $response->successful()) {
            Log::error('Telegram forwardMessage failed', [
                'to' => $toChatId,
                'from' => $fromChatId,
                'response' => $response->body(),
            ]);

            return null;
        }

        $id = $response->json('result.message_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Mavjud xabarga reply qilib javob yuboradi.
     */
    public function replyToMessage(string $token, int $chatId, int $replyToMessageId, string $text): bool
    {
        $response = $this->http()->post(self::API_BASE.$token.'/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_to_message_id' => $replyToMessageId,
        ]);

        if (! $response->successful()) {
            Log::error('Telegram replyToMessage failed', [
                'chat_id' => $chatId,
                'response' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Guruh adminlarining Telegram user ID'lari.
     *
     * @return array<int,int> xato bo'lsa bo'sh massiv
     */
    public function getChatAdministrators(string $token, int $chatId): array
    {
        $response = $this->http()->get(self::API_BASE.$token.'/getChatAdministrators', [
            'chat_id' => $chatId,
        ]);

        if (! $response->successful()) {
            Log::warning('Telegram getChatAdministrators failed', [
                'chat_id' => $chatId,
                'response' => $response->body(),
            ]);

            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $a): ?int => isset($a['user']['id']) ? (int) $a['user']['id'] : null,
            $response->json('result') ?? [],
        )));
    }

    public function setWebhook(string $token, string $url): array
    {
        $response = $this->http()->post(self::API_BASE . $token . '/setWebhook', [
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
