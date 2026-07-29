<?php

namespace App\Services;

use App\Exceptions\ActiveUserPurgeForbiddenException;
use App\Exceptions\InvalidRegistrationCodeException;
use App\Exceptions\PhoneAlreadyRegisteredException;
use App\Models\User;
use App\Support\DatabaseIntegrityException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * OTP tasdiqlangach yangi foydalanuvchi yaratadi.
     * Soft-deleted telefon band bo'lsa, eski akkauntni anonimlashtiradi (restore qilmaydi).
     *
     * @throws PhoneAlreadyRegisteredException
     * @throws InvalidRegistrationCodeException
     */
    public function register(string $phone, string $code, string $name, string $password): User
    {
        if (User::where('phone', $phone)->exists()) {
            throw new PhoneAlreadyRegisteredException;
        }

        try {
            return DB::transaction(function () use ($phone, $code, $name, $password) {
                $existing = User::withTrashed()
                    ->where('phone', $phone)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && ! $existing->trashed()) {
                    throw new PhoneAlreadyRegisteredException;
                }

                $verified = $this->otpService->validate($phone, $code);
                if ($verified === null) {
                    throw new InvalidRegistrationCodeException;
                }

                if ($existing !== null && $existing->trashed()) {
                    $this->purgeLegacyAccount($existing);
                }

                return User::create([
                    'name' => $name,
                    'phone' => $phone,
                    'password' => $password,
                    'is_accepted_policy' => true,
                    'phone_verified_at' => now(),
                ]);
            });
        } catch (PhoneAlreadyRegisteredException|InvalidRegistrationCodeException $e) {
            throw $e;
        } catch (QueryException $e) {
            if (DatabaseIntegrityException::isDuplicateKeyViolation($e)) {
                throw new PhoneAlreadyRegisteredException(previous: $e);
            }

            throw $e;
        }
    }

    /**
     * Akkauntni soft-delete qiladi: tokenlar, auth identity va PII tozalanadi.
     */
    public function deleteAccount(User $user): void
    {
        $localAvatarPath = $this->localAvatarPath($user);

        DB::transaction(function () use ($user) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $lockedUser->tokens()->delete();
            $this->anonymizeUserIdentifiers($lockedUser);

            if (! $lockedUser->trashed()) {
                $lockedUser->delete();
            }
        });

        $this->scheduleLocalAvatarDeletion($localAvatarPath);
    }

    /**
     * Legacy (faqat soft-deleted) akkauntni qayta ishlatishdan oldin tozalaydi.
     *
     * @throws ActiveUserPurgeForbiddenException
     */
    public function purgeLegacyAccount(User $user): void
    {
        $localAvatarPath = $this->localAvatarPath($user);

        DB::transaction(function () use ($user) {
            $lockedUser = User::withTrashed()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUser->trashed()) {
                throw new ActiveUserPurgeForbiddenException(
                    'purgeLegacyAccount can only be used for soft-deleted users.'
                );
            }

            $lockedUser->tokens()->delete();
            $this->anonymizeUserIdentifiers($lockedUser);
        });

        $this->scheduleLocalAvatarDeletion($localAvatarPath);
    }

    private function anonymizeUserIdentifiers(User $user): void
    {
        $user->authIdentities()->delete();

        $user->forceFill([
            'name' => null,
            'email' => null,
            'phone' => $this->tombstonePhone($user),
            'password' => Str::password(64),
            'remember_token' => null,
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'google_id' => null,
            'apple_id' => null,
            'avatar_url' => null,
            'phone_verified_at' => null,
            'email_verified_at' => null,
        ])->save();
    }

    /**
     * 32 belgigacha, user UUID asosida collision-resistant tombstone.
     */
    private function tombstonePhone(User $user): string
    {
        return 'd'.substr(str_replace('-', '', $user->id), 0, 31);
    }

    private function localAvatarPath(User $user): ?string
    {
        $avatarUrl = $user->avatar_url;

        if (! is_string($avatarUrl) || $avatarUrl === '' || str_starts_with($avatarUrl, 'http')) {
            return null;
        }

        return $avatarUrl;
    }

    private function scheduleLocalAvatarDeletion(?string $path): void
    {
        if ($path === null) {
            return;
        }

        DB::afterCommit(function () use ($path) {
            Storage::disk('public')->delete($path);
        });
    }
}
