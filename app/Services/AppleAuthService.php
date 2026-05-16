<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Apple "Sign in with Apple" identity token (JWT) ni tekshiradi.
 *
 * Bosqichlar:
 *  1. Apple JWKS endpoint'idan public kalitlar olinadi (24 soatga cache).
 *  2. Kelgan identity_token JWKS bilan tekshiriladi (RS256).
 *  3. `iss`, `aud`, `exp` claim'lari validate qilinadi.
 *  4. `sub` (Apple user UID) va `email` qaytariladi.
 */
class AppleAuthService
{
    private const CACHE_KEY = 'apple_jwks_keys_v1';
    private const CACHE_TTL = 60 * 60 * 24; // 24h

    /**
     * @return array{sub:string, email:?string, email_verified:bool, raw:array<string,mixed>}
     *
     * @throws RuntimeException
     */
    public function verifyIdentityToken(string $identityToken): array
    {
        $jwks = $this->getJwks();
        $keys = JWK::parseKeySet($jwks);

        try {
            $payload = JWT::decode($identityToken, $keys);
        } catch (\Throwable $e) {
            throw new RuntimeException('Apple identity token is invalid: '.$e->getMessage(), 401, $e);
        }

        $payloadArr = json_decode(json_encode($payload), true);

        $expectedIssuer = config('services.apple.issuer', 'https://appleid.apple.com');
        if (! isset($payloadArr['iss']) || $payloadArr['iss'] !== $expectedIssuer) {
            throw new RuntimeException('Apple identity token has invalid issuer', 401);
        }

        $allowedAudiences = (array) config('services.apple.client_ids', []);
        $aud = $payloadArr['aud'] ?? null;
        if (! $aud || ! in_array($aud, $allowedAudiences, true)) {
            throw new RuntimeException('Apple identity token has invalid audience', 401);
        }

        $sub = $payloadArr['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            throw new RuntimeException('Apple identity token has no subject', 401);
        }

        return [
            'sub' => $sub,
            'email' => isset($payloadArr['email']) && is_string($payloadArr['email'])
                ? strtolower($payloadArr['email'])
                : null,
            'email_verified' => filter_var($payloadArr['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'raw' => $payloadArr,
        ];
    }

    /**
     * Apple JWKS ni olib, cachelaydi.
     *
     * @return array<string,mixed>
     */
    private function getJwks(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $url = config('services.apple.jwks_url', 'https://appleid.apple.com/auth/keys');
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('Could not fetch Apple JWKS', 503);
            }

            $json = $response->json();
            if (! is_array($json) || ! isset($json['keys'])) {
                throw new RuntimeException('Apple JWKS response is malformed', 502);
            }

            return $json;
        });
    }
}
