<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuthProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\AppleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppleAuthController extends Controller
{
    public function __construct(
        private readonly AppleAuthService $appleAuth,
    ) {}

    /**
     * POST /v1/auth/apple
     *
     * Apple "Sign in with Apple" identity token bilan kirish/ro'yxatdan o'tish.
     *
     * Body:
     *  - identity_token (required, string)  — Apple Authorization Credential JWT
     *  - name (optional, string)            — faqat birinchi marta kelganda Apple beradi
     *  - email (optional, string)           — faqat birinchi marta kelganda Apple beradi
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identity_token' => ['required', 'string'],
            'name'           => ['nullable', 'string', 'max:120'],
            'email'          => ['nullable', 'email', 'max:191'],
        ]);

        try {
            $payload = $this->appleAuth->verifyIdentityToken($request->string('identity_token'));
        } catch (RuntimeException $e) {
            return $this->error(__('api.auth.apple_invalid_token'), 401);
        }

        $appleSub = $payload['sub'];
        // Apple email faqat birinchi loginda keladi — keyin yo'q.
        $tokenEmail   = $payload['email'];
        $requestEmail = $request->filled('email') ? strtolower($request->string('email')) : null;
        $email        = $tokenEmail ?: $requestEmail;
        $name         = $request->filled('name') ? trim($request->string('name')) : null;

        $user = DB::transaction(function () use ($appleSub, $email, $name, $payload) {
            // 1) AuthIdentity bo'yicha izlash
            $identity = AuthIdentity::with('user')
                ->where('provider', AuthProvider::Apple->value)
                ->where('provider_subject', $appleSub)
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
                    'name'              => $name,
                    'email'             => $email,
                    'apple_id'          => $appleSub,
                    'is_accepted_policy'=> true,
                    'email_verified_at' => $payload['email_verified'] ? now() : null,
                ]);
            } else {
                $updates = ['apple_id' => $appleSub];
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
                'provider'         => AuthProvider::Apple->value,
                'provider_subject' => $appleSub,
                'metadata'         => [
                    'email'          => $email,
                    'email_verified' => $payload['email_verified'],
                ],
                'verified_at'      => now(),
            ]);

            return $user;
        });

        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return $this->success([
            'user'  => new UserResource($user->refresh()),
            'token' => $token,
        ], __('api.auth.apple_login_success'));
    }
}
