<?php

namespace App\Services;

use App\Models\PhoneVerificationCode;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    private const CODE_LENGTH   = 4;
    private const EXPIRES_MIN   = 2;
    private const MAX_ATTEMPTS  = 5;
    private const RESEND_SEC    = 60;

    /**
     * Generate and store a new OTP for the given phone.
     * Deletes previous unverified codes for the same phone.
     */
    public function generate(string $phone): PhoneVerificationCode
    {
        // Throttle: block if a valid code was sent < RESEND_SEC ago
        $recent = PhoneVerificationCode::where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->where('created_at', '>', Carbon::now()->subSeconds(self::RESEND_SEC))
            ->latest()
            ->first();

        if ($recent) {
            $waitSeconds = self::RESEND_SEC - Carbon::now()->diffInSeconds($recent->created_at, false);
            abort(429, "Iltimos, {$waitSeconds} soniya kuting.");
        }

        // Remove old codes for this phone
        PhoneVerificationCode::where('phone', $phone)
            ->whereNull('verified_at')
            ->delete();

        $code = str_pad((string) random_int(0, 9999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        return PhoneVerificationCode::create([
            'phone'      => $phone,
            'code'       => $code,
            'expires_at' => Carbon::now()->addMinutes(self::EXPIRES_MIN),
        ]);
    }

    /**
     * Validate the given code for the given phone.
     * Returns the record on success, null on failure.
     */
    public function validate(string $phone, string $code): ?PhoneVerificationCode
    {
        $record = PhoneVerificationCode::where('phone', $phone)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $record) {
            return null;
        }

        if ($record->isExpired()) {
            $record->delete();
            return null;
        }

        if ($record->hasExceededAttempts()) {
            return null;
        }

        if ($record->code !== $code) {
            $record->increment('attempts');
            return null;
        }

        $record->update(['verified_at' => Carbon::now()]);
        return $record;
    }

    /**
     * Check if phone has a recently verified (unused) code within registration window.
     */
    public function isRecentlyVerified(string $phone): bool
    {
        return PhoneVerificationCode::where('phone', $phone)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', Carbon::now()->subMinutes(10))
            ->exists();
    }
}
