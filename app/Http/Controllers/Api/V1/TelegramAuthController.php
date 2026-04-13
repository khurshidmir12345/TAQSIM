<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\SystemBot;
use App\Models\TelegramAuthSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    public function createSession(): JsonResponse
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
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        return $this->success([
            'session_token' => $sessionToken,
            'bot_username' => $bot->username,
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
}
