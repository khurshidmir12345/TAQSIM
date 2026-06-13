<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuthProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\DeviceService;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuth,
        private readonly DeviceService $devices,
    ) {}

    /**
     * POST /v1/auth/google
     *
     * Google Sign-In id_token bilan kirish/ro'yxatdan o'tish.
     *
     * Body:
     *  - id_token (required, string) — Google ID token (JWT)
     *  - name (optional, string)     — zaxira (token ichida name odatda keladi)
     *  - email (optional, string)    — zaxira
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'name'     => ['nullable', 'string', 'max:120'],
            'email'    => ['nullable', 'email', 'max:191'],
        ]);

        try {
            $payload = $this->googleAuth->verifyIdToken($request->string('id_token'));
        } catch (RuntimeException $e) {
            Log::warning('Google sign-in token verification failed', [
                'reason' => $e->getMessage(),
            ]);

            return $this->error(__('api.auth.google_invalid_token'), 401);
        }

        $googleSub    = $payload['sub'];
        $tokenEmail   = $payload['email'];
        $requestEmail = $request->filled('email') ? strtolower($request->string('email')) : null;
        $email        = $tokenEmail ?: $requestEmail;
        $name         = $payload['name']
            ?: ($request->filled('name') ? trim($request->string('name')) : null);

        $user = DB::transaction(function () use ($googleSub, $email, $name, $payload) {
            // 1) AuthIdentity bo'yicha izlash
            $identity = AuthIdentity::with('user')
                ->where('provider', AuthProvider::Google->value)
                ->where('provider_subject', $googleSub)
                ->first();

            if ($identity?->user) {
                $user = $identity->user;
                if ($name && empty($user->name)) {
                    $user->fill(['name' => $name])->save();
                }

                return $user;
            }

            // 2) Email orqali topilsa (foydalanuvchi avval boshqa usulda kirgan) — link qilamiz
            $user = $email ? User::where('email', $email)->first() : null;

            // 3) Hech qayerda topilmasa — yangi user
            if (! $user) {
                $user = User::create([
                    'name'               => $name,
                    'email'              => $email,
                    'google_id'          => $googleSub,
                    'is_accepted_policy' => true,
                    'email_verified_at'  => $payload['email_verified'] ? now() : null,
                ]);
            } else {
                $updates = ['google_id' => $googleSub];
                if ($name && empty($user->name)) {
                    $updates['name'] = $name;
                }
                if ($payload['email_verified'] && empty($user->email_verified_at)) {
                    $updates['email_verified_at'] = now();
                }
                $user->fill($updates)->save();
            }

            AuthIdentity::create([
                'user_id'          => $user->id,
                'provider'         => AuthProvider::Google->value,
                'provider_subject' => $googleSub,
                'metadata'         => [
                    'email'          => $email,
                    'email_verified' => $payload['email_verified'],
                    'picture'        => $payload['picture'],
                ],
                'verified_at'      => now(),
            ]);

            return $user;
        });

        // Multi-device: eski sessiyalar saqlanadi.
        $newToken = $user->createToken('mobile');
        $this->devices->record($user, $newToken, $request);

        return $this->success([
            'user'  => new UserResource($user->refresh()),
            'token' => $newToken->plainTextToken,
        ], __('api.auth.google_login_success'));
    }
}
