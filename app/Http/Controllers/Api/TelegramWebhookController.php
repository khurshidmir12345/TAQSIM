<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemBot;
use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\SupportRelayService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly SupportRelayService $support,
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

        // Bot guruhga qo'shildi yoki chiqarildi — guruhlar ro'yxati yangilanadi.
        if (isset($update['my_chat_member'])) {
            $this->support->syncChatMembership($bot, $update['my_chat_member']);

            return response()->json(['ok' => true]);
        }

        if (isset($update['message'])) {
            $this->processMessage($bot, $update['message']);
        }

        return response()->json(['ok' => true]);
    }

    private function processMessage(SystemBot $bot, array $message): void
    {
        $chatType = $message['chat']['type'] ?? 'private';

        // Guruhdagi xabarlardan faqat support reply'lari qiziqtiradi.
        if ($chatType !== 'private') {
            if ($this->support->isSupportGroup($bot, (int) $message['chat']['id'])) {
                $this->support->relayFromAdmin($bot, $message);
            }

            return;
        }

        $chatId = $message['chat']['id'];

        if (isset($message['contact'])) {
            $this->handleContact($bot, $chatId, $message['contact'], $message);

            return;
        }

        $text = $message['text'] ?? '';

        if (str_starts_with($text, '/start')) {
            // Login oqimi faqat ro'yxatdan o'tish botiga tegishli.
            if ($bot->type === 'register') {
                $this->handleStart($bot, $chatId, $text, $message);

                return;
            }

            $this->telegram->sendMessage(
                $bot->token,
                $chatId,
                "Assalomu alaykum! \u{1F44B}\n\n"
                ."Bu — <b>TAQSEEM</b> qo'llab-quvvatlash xizmati.\n"
                ."Savolingizni shu yerga yozing, tez orada javob beramiz. \u{1F4AC}",
            );

            return;
        }

        // Ro'yxatdan o'tish boti login oqimini boshqaradi; boshqa botlarda
        // shaxsiy xabar support savoli deb qaraladi.
        if ($bot->type !== 'register') {
            $this->support->relayFromUser($bot, $message);
        }
    }

    private function handleStart(SystemBot $bot, int $chatId, string $text, array $message): void
    {
        $parts = explode(' ', $text, 2);
        $sessionToken = trim($parts[1] ?? '') ?: null;

        if ($sessionToken) {
            $session = TelegramAuthSession::where('session_token', $sessionToken)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            // Connect oqimi: mavjud foydalanuvchiga bog'lash (telefon so'ralmaydi).
            if ($session && $session->type === 'connect') {
                $this->handleConnect($bot, $chatId, $session, $message);

                return;
            }

            if ($session) {
                $session->update(['telegram_chat_id' => $chatId]);
            }

            // Havola bilan kelgan, lekin sessiya topilmadi: muddati tugagan,
            // allaqachon ishlatilgan yoki boshqa muhitga (dev/prod) tegishli.
            //
            // Ilgari bu holatda telefon raqam so'ralardi — foydalanuvchi nima
            // bo'lganini tushunmasdi va ilova javob kutib qotib qolardi.
            if (! $session) {
                Log::info('Telegram: yaroqsiz start tokeni', [
                    'bot' => $bot->id,
                    'chat_id' => $chatId,
                ]);

                $this->telegram->sendMessage(
                    $bot->token,
                    $chatId,
                    "\u{23F3} <b>Havola eskirgan</b>\n\n"
                    ."Ulanish havolasining muddati tugagan yoki u allaqachon ishlatilgan.\n\n"
                    ."Ilovaga qaytib, <b>Telegramni ulash</b> tugmasini qaytadan bosing.",
                );

                return;
            }
        }

        $firstName = $message['from']['first_name'] ?? 'Foydalanuvchi';

        $this->telegram->requestContact(
            $bot->token,
            $chatId,
            "Assalomu alaykum, {$firstName}! \u{1F44B}\n\n"
            ."\u{1F4F2} <b>TAQSEEM</b> ilovasiga kirish uchun telefon raqamingizni yuboring.\n\n"
            ."Pastdagi tugmani bosing \u{1F447}",
        );
    }

    /**
     * Connect oqimi: mavjud foydalanuvchiga Telegram hisobini bog'laydi.
     * Yangi hisob ochmaydi va token bermaydi — faqat telegram_chat_id va
     * username'ni sessiya egasiga yozadi.
     */
    private function handleConnect(SystemBot $bot, int $chatId, TelegramAuthSession $session, array $message): void
    {
        $user = $session->user;

        if (! $user) {
            $session->update(['status' => 'failed']);
            $this->telegram->sendMessage(
                $bot->token,
                $chatId,
                "\u{274C} Foydalanuvchi topilmadi. Iltimos, ilovadan qaytadan urinib ko'ring.",
            );

            return;
        }

        // Bu Telegram hisobi boshqa foydalanuvchiga bog'langan bo'lsa — rad etamiz.
        $conflict = User::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->first();

        if ($conflict) {
            $session->update(['status' => 'failed']);
            $this->telegram->sendMessage(
                $bot->token,
                $chatId,
                "\u{26A0}\u{FE0F} <b>Diqqat!</b>\n\n"
                ."Bu Telegram hisobi allaqachon boshqa TAQSEEM foydalanuvchisiga bog'langan.",
            );

            return;
        }

        $telegramUsername = $message['from']['username'] ?? null;

        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $telegramUsername,
        ]);

        $session->update([
            'telegram_chat_id' => $chatId,
            'status' => 'completed',
        ]);

        $this->telegram->sendMessage(
            $bot->token,
            $chatId,
            "\u{2705} <b>Muvaffaqiyatli ulandi!</b>\n\n"
            ."Telegram hisobingiz <b>TAQSEEM</b> ilovasiga bog'landi.\n"
            ."Endi muhim bildirishnomalarni shu yerda olasiz. \u{1F514}\n\n"
            ."Ilovaga qaytishingiz mumkin. \u{1F4F2}",
        );
    }

    /**
     * Kontakt xavfsizligi: yuborilgan kontakt haqiqatan ham xabar yuborgan
     * foydalanuvchining o'ziga tegishli bo'lishi shart (Telegram "so'rov
     * tugmasi" orqali bosilganda buni ta'minlaydi, ammo forward qilingan
     * begona kontaktlarni rad etish uchun serverda ham tekshiramiz).
     */
    private function isOwnContact(array $contact, array $message): bool
    {
        $fromId = $message['from']['id'] ?? null;
        $contactUserId = $contact['user_id'] ?? null;

        if ($fromId === null || $contactUserId === null) {
            return false;
        }

        return (int) $contactUserId === (int) $fromId;
    }

    private function handleContact(SystemBot $bot, int $chatId, array $contact, array $message): void
    {
        if (! $this->isOwnContact($contact, $message)) {
            $this->telegram->sendMessage(
                $bot->token,
                $chatId,
                "\u{26A0}\u{FE0F} Faqat <b>o'zingizga tegishli</b> telefon raqamni yuborishingiz mumkin.\n\n"
                ."Pastdagi tugma orqali o'z raqamingizni yuboring.",
            );

            return;
        }

        $phone = $contact['phone_number'];
        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }
        $firstName = $contact['first_name'] ?? $message['from']['first_name'] ?? null;
        $telegramUsername = $message['from']['username'] ?? null;

        // Faol (pending, muddati o'tmagan, login turidagi, shu chatga bog'langan)
        // sessiya bo'lmasa — hisob yaratish yoki token berish umuman amalga
        // oshmaydi. Qator lokini (lockForUpdate) takroriy contact xabarlarida
        // (masalan, Telegram'ning qayta yetkazishi) bir nechta token
        // yaratilishining oldini oladi: ikkinchi so'rov birinchisi tugaguncha
        // bloklanadi va status endi "pending" bo'lmaganini ko'rib chiqadi.
        $outcome = DB::transaction(function () use ($chatId, $phone, $firstName, $telegramUsername) {
            $session = TelegramAuthSession::where('telegram_chat_id', $chatId)
                ->where('type', 'login')
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->latest()
                ->first();

            if (! $session) {
                return ['result' => 'no_session'];
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

            if ($user->isBlocked()) {
                $session->update(['status' => 'failed']);

                return ['result' => 'blocked'];
            }

            // Multi-device: eski sessiyalar saqlanadi. Webhook Telegram
            // serveridan keladi — haqiqiy qurilma metama'lumoti yo'q, shuning
            // uchun sessiya platformasiga qarab "web" yoki "telegram" deb
            // belgilanadi (foydalanuvchi profilda ko'rib revoke qila oladi).
            $devicePlatform = $session->isWebClient() ? 'web' : 'telegram';

            $newToken = $user->createToken('mobile');
            $token = $newToken->plainTextToken;

            UserDevice::create([
                'user_id' => $user->id,
                'token_id' => $newToken->accessToken->getKey(),
                'platform' => $devicePlatform,
                'last_active_at' => now(),
            ]);

            $session->update([
                'phone' => $phone,
                'first_name' => $firstName,
                'user_id' => $user->id,
                'auth_token' => $token,
                'status' => 'completed',
            ]);

            return ['result' => 'completed', 'session' => $session];
        });

        if ($outcome['result'] === 'no_session') {
            $this->telegram->sendMessage(
                $bot->token,
                $chatId,
                "\u{274C} Faol so'rov topilmadi yoki muddati tugagan. Iltimos, ilovadan qaytadan urinib ko'ring.",
            );

            return;
        }

        if ($outcome['result'] === 'blocked') {
            $this->telegram->sendMessage(
                $bot->token,
                $chatId,
                "\u{1F6AB} <b>Hisobingiz bloklangan.</b>\n\n"
                ."Ilovaga kirish uchun administrator bilan bog'laning.",
            );

            return;
        }

        $this->telegram->sendWithInlineButton(
            $bot->token,
            $chatId,
            "\u{2705} <b>Muvaffaqiyatli!</b>\n\n"
            ."Siz tizimga kirdingiz. Endi ilovaga qaytishingiz mumkin.\n\n"
            ."Pastdagi tugmani bosing \u{1F447}",
            "\u{1F4F2} Ilovaga qaytish",
            $this->buildReturnUrl($outcome['session']),
        );
    }

    /**
     * Muvaffaqiyatli login'dan keyingi "ilovaga qaytish" manzili — faqat
     * serverda saqlangan sessiya platformasiga qarab hisoblanadi. Klient
     * tomondan yuborilgan har qanday arbitrary return URL'ga ishonilmaydi.
     */
    private function buildReturnUrl(TelegramAuthSession $session): string
    {
        if ($session->isWebClient()) {
            return rtrim((string) config('services.telegram.web_app_url'), '/').'/telegram';
        }

        return config('app.url').'/auth/app-redirect?session='.urlencode($session->session_token);
    }
}
