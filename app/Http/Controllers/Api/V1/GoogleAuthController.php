<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SocialAuthConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\DeviceService;
use App\Services\GoogleAuthService;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuth,
        private readonly SocialAuthService $socialAuth,
        private readonly DeviceService $devices,
    ) {}

    /**
     * POST /v1/auth/google
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191'],
        ]);

        try {
            $payload = $this->googleAuth->verifyIdToken($request->string('id_token'));
        } catch (RuntimeException $e) {
            Log::warning('Google sign-in token verification failed', [
                'reason' => $e->getMessage(),
            ]);

            return $this->error(__('api.auth.google_invalid_token'), 401);
        }

        $googleSub = $payload['sub'];
        $tokenEmail = $payload['email'];
        $requestDisplayEmail = $request->filled('email') ? strtolower($request->string('email')) : null;
        $name = $payload['name']
            ?: ($request->filled('name') ? trim($request->string('name')) : null);

        try {
            $user = $this->socialAuth->loginWithGoogle(
                $googleSub,
                $tokenEmail,
                $name,
                $payload['email_verified'],
                [
                    'email' => $tokenEmail ?? $requestDisplayEmail,
                    'email_verified' => $payload['email_verified'],
                    'picture' => $payload['picture'],
                ],
            );
        } catch (SocialAuthConflictException) {
            return $this->error(
                __('api.auth.social_auth_conflict'),
                409,
                ['code' => 'social_auth_conflict'],
            );
        }

        if ($user->isBlocked()) {
            return $this->error(__('api.errors.account_blocked'), 403, ['code' => 'account_blocked']);
        }

        $newToken = $user->createToken('mobile');
        $this->devices->record($user, $newToken, $request);

        return $this->success([
            'user' => new UserResource($user->refresh()),
            'token' => $newToken->plainTextToken,
        ], __('api.auth.google_login_success'));
    }
}
