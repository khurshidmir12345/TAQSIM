<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SendCodeRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DeviceService;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly SmsService $smsService,
        private readonly DeviceService $devices,
    ) {}

    /**
     * POST /v1/auth/send-code
     * Sends OTP to the given phone (stored in DB for now, SMS later).
     * Returns phone_exists: true if the phone is already registered (for UX redirect).
     */
    public function sendCode(SendCodeRequest $request): JsonResponse
    {
        $phone = $request->phone;
        $phoneExists = User::where('phone', $phone)->exists();

        $record = $this->otpService->generate($phone);

        // Send OTP via SMS (direct, no queue)
        $this->smsService->sendOtp($phone, $record->code);

        $message = $phoneExists
            ? __('api.auth.send_code_phone_exists')
            : __('api.auth.send_code_new');

        return $this->success([
            'phone_exists' => $phoneExists,
            'expires_in'   => 120,
        ], $message);
    }

    /**
     * POST /v1/auth/register
     * Verifies OTP then creates the user.
     * Returns 409 if phone already registered (mobile can redirect to login).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $phone = $request->phone;

        // Phone already exists → tell client to redirect to login
        if (User::where('phone', $phone)->exists()) {
            return $this->error(
                __('api.auth.register_phone_taken'),
                409,
                ['phone_exists' => true]
            );
        }

        // Validate OTP
        $verified = $this->otpService->validate($phone, $request->code);
        if (! $verified) {
            return $this->error(__('api.auth.invalid_code'), 422, [
                'code' => [__('api.auth.invalid_code')],
            ]);
        }

        $user = User::create([
            'name'             => $request->name,
            'phone'            => $phone,
            'password'         => $request->password,
            'is_accepted_policy' => true,
            'phone_verified_at'  => now(),
        ]);

        $newToken = $user->createToken('mobile');
        $this->devices->record($user, $newToken, $request);

        return $this->created([
            'user'  => new UserResource($user),
            'token' => $newToken->plainTextToken,
        ], __('api.auth.register_success'));
    }

    /**
     * POST /v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => [__('api.auth.login_invalid')],
            ]);
        }

        // Multi-device: eski sessiyalar o'chirilmaydi — har qurilma o'z tokeni bilan.
        $newToken = $user->createToken('mobile');
        $this->devices->record($user, $newToken, $request);

        return $this->success([
            'user'  => new UserResource($user),
            'token' => $newToken->plainTextToken,
        ], __('api.auth.login_success'));
    }

    /**
     * GET /v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * PUT /v1/auth/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success([
            'user' => new UserResource($user->fresh()),
        ], __('api.auth.profile_updated'));
    }

    /**
     * POST /v1/auth/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();

        // Eski faqat local public disk'dagi fayl bo'lsa tozalaymiz (OAuth URL'lar emas).
        if ($user->avatar_url && !str_starts_with($user->avatar_url, 'http')) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_url' => $path]);

        return $this->success([
            'user' => new UserResource($user->fresh()),
        ], __('api.auth.avatar_updated'));
    }

    /**
     * DELETE /v1/auth/avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url && !str_starts_with($user->avatar_url, 'http')) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->update(['avatar_url' => null]);

        return $this->success([
            'user' => new UserResource($user->fresh()),
        ], __('api.auth.avatar_removed'));
    }

    /**
     * PUT /v1/auth/password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->password]);

        // Xavfsizlik: parol o'zgarsa barcha sessiyalar bekor qilinadi
        // (user_devices yozuvlari ham cascade orqali o'chadi), so'ng joriy
        // qurilma uchun yangi token va qurilma yozuvi yaratiladi.
        $user->tokens()->delete();
        $newToken = $user->createToken('mobile');
        $this->devices->record($user, $newToken, $request);

        return $this->success([
            'token' => $newToken->plainTextToken,
        ], __('api.auth.password_changed'));
    }

    /**
     * DELETE /v1/auth/account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return $this->success(null, __('api.auth.account_deleted'));
    }

    /**
     * POST /v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Faqat joriy qurilma sessiyasi tugatiladi (multi-device). Sanctum
        // PersonalAccessToken bo'lsa o'chiriladi; TransientToken (test/oddiy
        // guard) bo'lsa o'tkazib yuboriladi.
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return $this->success(null, __('api.auth.logout_success'));
    }
}
