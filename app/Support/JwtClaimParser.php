<?php

namespace App\Support;

final class JwtClaimParser
{
    public static function parseEmailVerified(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['true', '1', 'yes'], true);
        }

        return false;
    }

    public static function parseEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email !== '' ? $email : null;
    }
}
