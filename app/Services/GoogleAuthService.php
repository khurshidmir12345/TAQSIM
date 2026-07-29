<?php

namespace App\Services;

use App\Support\JwtClaimParser;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Sign-In ID token (JWT) ni tekshiradi.
 *
 * Bosqichlar:
 *  1. Google JWKS endpoint'idan public kalitlar olinadi (24 soatga cache).
 *  2. Kelgan id_token JWKS bilan tekshiriladi (RS256).
 *  3. `iss`, `aud`, `exp` claim'lari validate qilinadi.
 *  4. `sub` (Google user UID), `email`, `name` qaytariladi.
 */
class GoogleAuthService
{
    private const CACHE_KEY = 'google_jwks_keys_v1';

    private const CACHE_TTL = 60 * 60 * 24; // 24h

    /**
     * @return array{sub:string, email:?string, email_verified:bool, name:?string, picture:?string, raw:array<string,mixed>}
     *
     * @throws RuntimeException
     */
    public function verifyIdToken(string $idToken): array
    {
        $jwks = $this->getJwks();
        $keys = JWK::parseKeySet($jwks);

        try {
            $payload = JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            throw new RuntimeException('Google id token is invalid: '.$e->getMessage(), 401, $e);
        }

        $payloadArr = json_decode(json_encode($payload), true);

        $allowedIssuers = (array) config('services.google.issuers', [
            'https://accounts.google.com',
            'accounts.google.com',
        ]);
        if (! isset($payloadArr['iss']) || ! in_array($payloadArr['iss'], $allowedIssuers, true)) {
            throw new RuntimeException('Google id token has invalid issuer', 401);
        }

        $allowedAudiences = (array) config('services.google.client_ids', []);
        if (empty($allowedAudiences)) {
            throw new RuntimeException('Google client IDs are not configured', 500);
        }

        $aud = $payloadArr['aud'] ?? null;
        if (! $aud || ! in_array($aud, $allowedAudiences, true)) {
            throw new RuntimeException('Google id token has invalid audience', 401);
        }

        $sub = $payloadArr['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            throw new RuntimeException('Google id token has no subject', 401);
        }

        $email = JwtClaimParser::parseEmail($payloadArr['email'] ?? null);
        $emailVerified = JwtClaimParser::parseEmailVerified($payloadArr['email_verified'] ?? null);

        if ($email === null) {
            $emailVerified = false;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => $emailVerified,
            'name' => isset($payloadArr['name']) && is_string($payloadArr['name']) && $payloadArr['name'] !== ''
                ? trim($payloadArr['name'])
                : null,
            'picture' => isset($payloadArr['picture']) && is_string($payloadArr['picture'])
                ? $payloadArr['picture']
                : null,
            'raw' => $payloadArr,
        ];
    }

    /**
     * Google JWKS ni olib, cachelaydi.
     *
     * @return array<string,mixed>
     */
    private function getJwks(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $url = config('services.google.jwks_url', 'https://www.googleapis.com/oauth2/v3/certs');
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('Could not fetch Google JWKS', 503);
            }

            $json = $response->json();
            if (! is_array($json) || ! isset($json['keys'])) {
                throw new RuntimeException('Google JWKS response is malformed', 502);
            }

            return $json;
        });
    }
}
