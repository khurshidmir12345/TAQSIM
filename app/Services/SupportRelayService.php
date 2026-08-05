<?php

namespace App\Services;

use App\Models\BotChat;
use App\Models\SupportThread;
use App\Models\SystemBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Support oqimi: foydalanuvchi botga yozadi → xabar support guruhiga forward
 * qilinadi. Admin guruhda o'sha xabarga reply qilsa → javob foydalanuvchiga
 * qaytadi.
 *
 * Bot bir nechta guruhda bo'lishi mumkin, shuning uchun guruhning vazifasi
 * `bot_chats.purpose` orqali aniqlanadi (`support` / `notify`).
 */
class SupportRelayService
{
    /** Admin ro'yxati keshi (soniya). */
    private const ADMIN_CACHE_TTL = 300;

    /** Shu muddat ichida yangi yozuv bo'lmasa, foydalanuvchiga tasdiq yuboriladi. */
    private const ACK_WINDOW_HOURS = 24;

    public function __construct(
        private readonly TelegramBotService $telegram,
    ) {}

    /**
     * Foydalanuvchining shaxsiy xabarini support guruhiga uzatadi.
     *
     * @return bool uzatildimi (guruh sozlanmagan bo'lsa false)
     */
    public function relayFromUser(SystemBot $bot, array $message): bool
    {
        $group = $this->supportChat($bot);

        if (! $group) {
            Log::warning('Support guruhi sozlanmagan', ['bot' => $bot->id]);

            return false;
        }

        $userChatId = (int) $message['chat']['id'];
        $messageId = (int) $message['message_id'];

        $groupMessageId = $this->telegram->forwardMessage(
            $bot->token,
            (int) $group->chat_id,
            $userChatId,
            $messageId,
        );

        if ($groupMessageId === null) {
            return false;
        }

        $isNewConversation = ! SupportThread::query()
            ->where('user_chat_id', (string) $userChatId)
            ->where('created_at', '>=', now()->subHours(self::ACK_WINDOW_HOURS))
            ->exists();

        SupportThread::create([
            'system_bot_id' => $bot->id,
            'group_chat_id' => (string) $group->chat_id,
            'group_message_id' => $groupMessageId,
            'user_chat_id' => (string) $userChatId,
            'user_name' => trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')) ?: null,
            'user_username' => $message['from']['username'] ?? null,
        ]);

        // Har bir xabarga emas — faqat suhbat boshida tasdiq yuboriladi.
        if ($isNewConversation) {
            $this->telegram->sendMessage(
                $bot->token,
                $userChatId,
                "\u{2705} Xabaringiz qabul qilindi.\n\n"
                ."Tez orada javob beramiz. \u{1F550}",
            );
        }

        return true;
    }

    /**
     * Guruhdagi reply'ni foydalanuvchiga qaytaradi.
     *
     * @return bool javob yuborildimi
     */
    public function relayFromAdmin(SystemBot $bot, array $message): bool
    {
        $replyTo = $message['reply_to_message'] ?? null;

        if (! $replyTo) {
            return false;
        }

        $groupChatId = (string) $message['chat']['id'];

        $thread = SupportThread::query()
            ->where('group_chat_id', $groupChatId)
            ->where('group_message_id', (int) $replyTo['message_id'])
            ->first();

        // Support bilan bog'liq bo'lmagan oddiy guruh suhbati — e'tiborsiz.
        if (! $thread) {
            return false;
        }

        $senderId = (int) ($message['from']['id'] ?? 0);

        if (! $this->isAdmin($bot, (int) $message['chat']['id'], $senderId)) {
            return false;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '') {
            $this->telegram->replyToMessage(
                $bot->token,
                (int) $message['chat']['id'],
                (int) $message['message_id'],
                "\u{26A0}\u{FE0F} Hozircha faqat matnli javob yuborish mumkin.",
            );

            return false;
        }

        $sent = $this->telegram->sendMessage(
            $bot->token,
            (int) $thread->user_chat_id,
            "\u{1F4AC} <b>Qo'llab-quvvatlash xizmati:</b>\n\n".$this->esc($text),
        );

        // Adminlar javob yetib borgan-bormaganini ko'rib tursin.
        $this->telegram->replyToMessage(
            $bot->token,
            (int) $message['chat']['id'],
            (int) $message['message_id'],
            $sent
                ? "\u{2705} Javob yuborildi."
                : "\u{274C} Javob yuborilmadi — foydalanuvchi botni bloklagan bo'lishi mumkin.",
        );

        return $sent;
    }

    /**
     * Guruh bot uchun support guruhi sifatida belgilanganmi.
     */
    public function isSupportGroup(SystemBot $bot, int $chatId): bool
    {
        return BotChat::query()
            ->where('system_bot_id', $bot->id)
            ->where('chat_id', (string) $chatId)
            ->active()
            ->purpose(BotChat::PURPOSE_SUPPORT)
            ->exists();
    }

    /**
     * Bot guruhga qo'shilganda/chiqarilganda `bot_chats` ni yangilaydi.
     * `purpose` admin panelda tanlanadi — bu yerda tegilmaydi.
     */
    public function syncChatMembership(SystemBot $bot, array $myChatMember): void
    {
        $chat = $myChatMember['chat'] ?? null;

        if (! $chat || ! in_array($chat['type'] ?? '', ['group', 'supergroup', 'channel'], true)) {
            return;
        }

        $status = $myChatMember['new_chat_member']['status'] ?? '';
        $isMember = in_array($status, ['member', 'administrator', 'creator'], true);

        BotChat::query()->updateOrCreate(
            [
                'system_bot_id' => $bot->id,
                'chat_id' => (string) $chat['id'],
            ],
            [
                'title' => $chat['title'] ?? null,
                'is_active' => $isMember,
            ],
        );
    }

    private function supportChat(SystemBot $bot): ?BotChat
    {
        return BotChat::query()
            ->where('system_bot_id', $bot->id)
            ->active()
            ->purpose(BotChat::PURPOSE_SUPPORT)
            ->latest()
            ->first();
    }

    /**
     * Adminlar ro'yxati keshlanadi. Telegram xato qaytarsa bo'sh ro'yxat
     * KESHLANMAYDI — aks holda vaqtinchalik nosozlik 5 daqiqaga barcha
     * javoblarni to'sib qo'yardi.
     */
    private function isAdmin(SystemBot $bot, int $chatId, int $userId): bool
    {
        if ($userId === 0) {
            return false;
        }

        $key = "tg:admins:{$bot->id}:{$chatId}";
        $admins = Cache::get($key);

        if ($admins === null) {
            $admins = $this->telegram->getChatAdministrators($bot->token, $chatId);

            if ($admins !== []) {
                Cache::put($key, $admins, self::ADMIN_CACHE_TTL);
            }
        }

        return in_array($userId, $admins, true);
    }

    /** Xabar HTML rejimida ketadi — foydalanuvchi matni ekranlanadi. */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
