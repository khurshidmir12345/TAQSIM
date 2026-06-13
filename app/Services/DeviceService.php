<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;

/**
 * Multi-device sessiya boshqaruvi.
 *
 * Login/register/oauth paytida yangi token yaratilganda qurilma metama'lumotini
 * saqlaydi; profil "Qurilmalar" bo'limi uchun ro'yxat beradi va tokenni revoke qiladi.
 */
class DeviceService
{
    /** Token yaratilgach qurilma yozuvini saqlaydi. */
    public function record(User $user, NewAccessToken $newToken, Request $request): UserDevice
    {
        return UserDevice::create([
            'user_id' => $user->id,
            'token_id' => $newToken->accessToken->getKey(),
            'device_name' => $this->deviceName($request),
            'platform' => $this->str($request, 'platform', 'X-Device-Platform'),
            'app_version' => $this->str($request, 'app_version', 'X-App-Version'),
            'ip' => $request->ip(),
            'last_active_at' => now(),
        ]);
    }

    /**
     * Foydalanuvchining barcha aktiv sessiyalari (qurilmalari).
     * personal_access_tokens — sessiya manbai; metama'lumot user_devices'dan.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(User $user, ?int $currentTokenId): array
    {
        $devices = UserDevice::where('user_id', $user->id)
            ->get()
            ->keyBy('token_id');

        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(function ($token) use ($devices, $currentTokenId) {
                $device = $devices->get($token->id);

                return [
                    'id' => (string) $token->id,
                    'device_name' => $device?->device_name,
                    'platform' => $device?->platform,
                    'app_version' => $device?->app_version,
                    'last_active_at' => ($token->last_used_at ?? $device?->last_active_at)?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                    'is_current' => $currentTokenId !== null && (int) $token->id === $currentTokenId,
                ];
            })
            ->all();
    }

    /**
     * Tanlangan qurilma (token) sessiyasini chiqaradi.
     * Faqat foydalanuvchining o'z tokenini o'chira oladi.
     */
    public function revoke(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->whereKey($tokenId)->first();

        if (! $token) {
            return false;
        }

        // user_devices yozuvi FK cascade orqali avtomatik o'chadi.
        $token->delete();

        return true;
    }

    private function deviceName(Request $request): ?string
    {
        $header = $request->header('X-Device-Name');
        if (is_string($header) && $header !== '') {
            return mb_substr($header, 0, 191);
        }

        $body = $request->input('device_name');

        return is_string($body) && $body !== '' ? mb_substr($body, 0, 191) : null;
    }

    private function str(Request $request, string $bodyKey, string $headerKey): ?string
    {
        $header = $request->header($headerKey);
        if (is_string($header) && $header !== '') {
            return mb_substr($header, 0, 32);
        }

        $body = $request->input($bodyKey);

        return is_string($body) && $body !== '' ? mb_substr($body, 0, 32) : null;
    }
}
