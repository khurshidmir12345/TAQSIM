<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTelegramSessionRequest;
use App\Http\Resources\UserResource;
use App\Models\SystemBot;
use App\Models\TelegramAuthSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    public function createSession(CreateTelegramSessionRequest $request): JsonResponse
    {
        $bot = SystemBot::where('type', 'register')
            ->where('is_active', true)
            ->first();

        if (! $bot) {
            return $this->error('Telegram bot is not configured', 503);
        }

        $sessionToken = Str::random(48);

        $session = TelegramAuthSession::create([
            'session_token' => $sessionToken,
            'client_platform' => $request->clientPlatform(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        return $this->success([
            'session_token' => $sessionToken,
            'bot_username' => $bot->username,
            'client_platform' => $session->client_platform,
            'expires_in' => 600,
        ]);
    }

    public function checkSession(string $sessionToken): JsonResponse
    {
        $session = TelegramAuthSession::where('session_token', $sessionToken)->first();

        if (! $session) {
            return $this->error('Session not found', 404);
        }

        if ($session->isExpired()) {
            if ($session->isPending()) {
                $session->update(['status' => 'expired']);
            }

            return $this->error('Session expired', 410);
        }

        if ($session->isPending()) {
            return $this->success(['status' => 'pending']);
        }

        return $this->success([
            'status' => 'completed',
            'token' => $session->auth_token,
            'user' => $session->user ? new UserResource($session->user) : null,
        ]);
    }

    /**
     * Mavjud (auth qilingan) foydalanuvchiga Telegramni bog'lash uchun sessiya
     * yaratadi. Login sessiyasidan farqi: yangi hisob ochmaydi, token bermaydi —
     * faqat joriy foydalanuvchiga telegram_chat_id'ni bog'laydi.
     */
    public function createConnectSession(Request $request): JsonResponse
    {
        $bot = SystemBot::where('type', 'register')
            ->where('is_active', true)
            ->first();

        if (! $bot) {
            return $this->error('Telegram bot is not configured', 503);
        }

        $sessionToken = Str::random(48);

        TelegramAuthSession::create([
            'session_token' => $sessionToken,
            'type' => 'connect',
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        return $this->success([
            'session_token' => $sessionToken,
            'bot_username' => $bot->username,
            'expires_in' => 600,
        ]);
    }

    /**
     * Connect sessiyasi holatini tekshiradi. Faqat sessiya egasi (joriy
     * foydalanuvchi) so'rashi mumkin. Tugagach yangilangan profilni qaytaradi.
     */
    public function connectStatus(Request $request, string $sessionToken): JsonResponse
    {
        $session = TelegramAuthSession::where('session_token', $sessionToken)
            ->where('type', 'connect')
            ->first();

        if (! $session) {
            return $this->error('Session not found', 404);
        }

        if ($session->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        if ($session->status === 'failed') {
            return $this->success(['status' => 'failed']);
        }

        if ($session->isExpired()) {
            if ($session->isPending()) {
                $session->update(['status' => 'expired']);
            }

            return $this->error('Session expired', 410);
        }

        if ($session->isPending()) {
            return $this->success(['status' => 'pending']);
        }

        return $this->success([
            'status' => 'completed',
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }
}
