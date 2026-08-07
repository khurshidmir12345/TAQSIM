<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 orqali push yuboradi.
 *
 * Service account JSON'dan JWT yasab, OAuth access token olinadi (55 daqiqa
 * keshlanadi). Qo'shimcha SDK paketi kerak emas — `firebase/php-jwt` loyihada
 * allaqachon bor (Google/Apple login uchun).
 */
class FcmService
{
    private const OAUTH_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    /** Tashqi xizmat osilib qolsa so'rov cho'zilib ketmasin. */
    private const TIMEOUT = 10;

    private const CONNECT_TIMEOUT = 3;

    /** Access token 1 soat yashaydi — 55 daqiqa keshlaymiz. */
    private const TOKEN_TTL = 3300;

    /** Yuborish natijasi. */
    public const SENT = 'sent';

    /** Token o'lik — bazadan o'chirish kerak. */
    public const INVALID = 'invalid';

    /** Vaqtinchalik nosozlik — token saqlanadi. */
    public const FAILED = 'failed';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  array<string,scalar>  $data  bosilganda qayerga o'tishni bildiruvchi qo'shimcha
     * @return string self::SENT | self::INVALID | self::FAILED
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return self::FAILED;
        }

        $accessToken = $this->accessToken($credentials);

        if ($accessToken === null) {
            return self::FAILED;
        }

        $response = Http::timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    // FCM `data` faqat string qabul qiladi.
                    'data' => array_map(static fn ($v): string => (string) $v, $data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => ['sound' => 'default'],
                    ],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return self::SENT;
        }

        $errorStatus = (string) $response->json('error.status');

        // Token o'chirilgan yoki ilova olib tashlangan — qayta urinish foydasiz.
        if ($response->status() === 404
            || in_array($errorStatus, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
            return self::INVALID;
        }

        Log::warning('FCM send failed', [
            'status' => $response->status(),
            'error' => $errorStatus,
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        return self::FAILED;
    }

    /**
     * Service account JSON.
     *
     * @return array{project_id:string,client_email:string,private_key:string}|null
     */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (! $path || ! is_string($path) || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)
            || ! isset($decoded['project_id'], $decoded['client_email'], $decoded['private_key'])) {
            Log::error('FCM service account fayli yaroqsiz', ['path' => $path]);

            return null;
        }

        return $decoded;
    }

    /**
     * Muvaffaqiyatsiz urinish KESHLANMAYDI — aks holda vaqtinchalik tarmoq
     * nosozligi 55 daqiqaga barcha push'larni to'sib qo'yardi.
     */
    private function accessToken(array $credentials): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();

        $jwt = JWT::encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::OAUTH_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key'], 'RS256');

        $response = Http::timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->asForm()
            ->post(self::OAUTH_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::error('FCM access token olinmadi', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return null;
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL);

        return $token;
    }
}
