<?php

namespace App\Services;

use App\Enums\AuthProvider;
use App\Exceptions\SocialAuthConflictException;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Support\DatabaseIntegrityException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SocialAuthService
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Google Sign-In orqali active userni qaytaradi yoki yangisini yaratadi.
     * Email linking faqat token ichidagi verified email orqali amalga oshiriladi.
     *
     * @param  array<string,mixed>  $identityMetadata
     *
     * @throws SocialAuthConflictException
     */
    public function loginWithGoogle(
        string $googleSub,
        ?string $tokenEmail,
        ?string $name,
        bool $emailVerified,
        array $identityMetadata = [],
    ): User {
        return $this->loginWithProvider(
            AuthProvider::Google,
            $googleSub,
            $tokenEmail,
            $name,
            $emailVerified,
            $identityMetadata,
        );
    }

    /**
     * Apple Sign-In orqali active userni qaytaradi yoki yangisini yaratadi.
     *
     * @param  array<string,mixed>  $identityMetadata
     *
     * @throws SocialAuthConflictException
     */
    public function loginWithApple(
        string $appleSub,
        ?string $tokenEmail,
        ?string $name,
        bool $emailVerified,
        array $identityMetadata = [],
    ): User {
        return $this->loginWithProvider(
            AuthProvider::Apple,
            $appleSub,
            $tokenEmail,
            $name,
            $emailVerified,
            $identityMetadata,
        );
    }

    /**
     * @param  array<string,mixed>  $identityMetadata
     *
     * @throws SocialAuthConflictException
     */
    private function loginWithProvider(
        AuthProvider $provider,
        string $subject,
        ?string $tokenEmail,
        ?string $name,
        bool $emailVerified,
        array $identityMetadata,
    ): User {
        try {
            return DB::transaction(function () use ($provider, $subject, $tokenEmail, $name, $emailVerified, $identityMetadata) {
                $identity = AuthIdentity::query()
                    ->where('provider', $provider->value)
                    ->where('provider_subject', $subject)
                    ->lockForUpdate()
                    ->first();

                if ($identity !== null) {
                    $linkedUser = User::withTrashed()
                        ->whereKey($identity->user_id)
                        ->lockForUpdate()
                        ->first();

                    if ($linkedUser !== null && ! $linkedUser->trashed()) {
                        $this->touchActiveSocialUser($linkedUser, $provider, $subject, $name, $emailVerified);

                        return $this->syncIdentity($provider, $subject, $linkedUser, $identityMetadata);
                    }

                    if ($linkedUser !== null && $linkedUser->trashed()) {
                        $this->authService->purgeLegacyAccount($linkedUser);
                    }
                }

                $legacyByProvider = $this->findLegacyUserByProviderId($provider, $subject);

                if ($legacyByProvider !== null && ! $legacyByProvider->trashed()) {
                    $this->linkLegacyActiveUser($legacyByProvider, $provider, $subject, $name, $emailVerified);

                    return $this->syncIdentity($provider, $subject, $legacyByProvider, $identityMetadata);
                }

                if ($legacyByProvider !== null && $legacyByProvider->trashed()) {
                    $this->authService->purgeLegacyAccount($legacyByProvider);
                }

                if ($emailVerified && $tokenEmail !== null) {
                    $legacyByEmail = $this->findLegacyUserByVerifiedEmail($tokenEmail);

                    if ($legacyByEmail !== null && ! $legacyByEmail->trashed()) {
                        if ($this->canLinkByVerifiedEmail($legacyByEmail, $tokenEmail)) {
                            $this->linkLegacyActiveUser($legacyByEmail, $provider, $subject, $name, $emailVerified);

                            return $this->syncIdentity($provider, $subject, $legacyByEmail, $identityMetadata);
                        }

                        $user = $this->createSocialUserSafely(
                            $provider,
                            $subject,
                            null,
                            $name,
                            false,
                        );

                        return $this->syncIdentity($provider, $subject, $user, $identityMetadata);
                    }

                    if ($legacyByEmail !== null && $legacyByEmail->trashed()) {
                        $this->authService->purgeLegacyAccount($legacyByEmail);
                    }
                }

                $user = $this->createSocialUserSafely(
                    $provider,
                    $subject,
                    $this->emailForNewUser($tokenEmail, $emailVerified),
                    $name,
                    $emailVerified,
                );

                return $this->syncIdentity($provider, $subject, $user, $identityMetadata);
            });
        } catch (QueryException $e) {
            if (! DatabaseIntegrityException::isDuplicateKeyViolation($e)) {
                throw $e;
            }

            $resolved = $this->resolveDuplicateSocialLogin($provider, $subject);
            if ($resolved !== null) {
                return $resolved;
            }

            throw new SocialAuthConflictException('Social login conflict.', previous: $e);
        }
    }

    private function findLegacyUserByProviderId(AuthProvider $provider, string $subject): ?User
    {
        $providerColumn = $this->providerColumn($provider);

        if ($providerColumn === null) {
            return null;
        }

        return User::withTrashed()
            ->where($providerColumn, $subject)
            ->lockForUpdate()
            ->first();
    }

    private function findLegacyUserByVerifiedEmail(string $tokenEmail): ?User
    {
        return User::withTrashed()
            ->where('email', $tokenEmail)
            ->lockForUpdate()
            ->first();
    }

    private function canLinkByVerifiedEmail(User $user, string $tokenEmail): bool
    {
        if ($user->email === null || strcasecmp($user->email, $tokenEmail) !== 0) {
            return false;
        }

        return $user->email_verified_at !== null;
    }

    private function emailForNewUser(?string $tokenEmail, bool $emailVerified): ?string
    {
        if (! $emailVerified || $tokenEmail === null) {
            return null;
        }

        $occupant = User::where('email', $tokenEmail)->first();
        if ($occupant !== null) {
            return null;
        }

        return $tokenEmail;
    }

    private function touchActiveSocialUser(
        User $user,
        AuthProvider $provider,
        string $subject,
        ?string $name,
        bool $emailVerified,
    ): void {
        $updates = $this->providerLinkUpdates($provider, $subject);

        if ($name && empty($user->name)) {
            $updates['name'] = $name;
        }

        if ($emailVerified && empty($user->email_verified_at)) {
            $updates['email_verified_at'] = now();
        }

        if ($updates !== []) {
            $user->fill($updates)->save();
        }
    }

    private function linkLegacyActiveUser(
        User $user,
        AuthProvider $provider,
        string $subject,
        ?string $name,
        bool $emailVerified,
    ): void {
        $this->touchActiveSocialUser($user, $provider, $subject, $name, $emailVerified);
    }

    /**
     * @throws SocialAuthConflictException
     */
    private function createSocialUserSafely(
        AuthProvider $provider,
        string $subject,
        ?string $email,
        ?string $name,
        bool $emailVerified,
    ): User {
        try {
            return $this->createSocialUser($provider, $subject, $email, $name, $emailVerified);
        } catch (QueryException $e) {
            if (! DatabaseIntegrityException::isDuplicateKeyViolation($e)) {
                throw $e;
            }

            $resolved = $this->resolveDuplicateSocialLogin($provider, $subject);
            if ($resolved !== null) {
                return $resolved;
            }

            throw new SocialAuthConflictException('Social login conflict.', previous: $e);
        }
    }

    private function createSocialUser(
        AuthProvider $provider,
        string $subject,
        ?string $email,
        ?string $name,
        bool $emailVerified,
    ): User {
        $attributes = [
            'name' => $name,
            'email' => $email,
            'is_accepted_policy' => true,
            'email_verified_at' => $emailVerified && $email !== null ? now() : null,
        ];

        $providerColumn = $this->providerColumn($provider);
        if ($providerColumn !== null) {
            $attributes[$providerColumn] = $subject;
        }

        return User::create($attributes);
    }

    /**
     * @param  array<string,mixed>  $identityMetadata
     */
    private function syncIdentity(
        AuthProvider $provider,
        string $subject,
        User $user,
        array $identityMetadata,
    ): User {
        try {
            AuthIdentity::updateOrCreate(
                [
                    'provider' => $provider->value,
                    'provider_subject' => $subject,
                ],
                [
                    'user_id' => $user->id,
                    'metadata' => $identityMetadata,
                    'verified_at' => now(),
                ],
            );
        } catch (QueryException $e) {
            if (! DatabaseIntegrityException::isDuplicateKeyViolation($e)) {
                throw $e;
            }

            $resolved = $this->resolveDuplicateSocialLogin($provider, $subject);
            if ($resolved !== null) {
                return $resolved;
            }

            throw new SocialAuthConflictException('Social login conflict.', previous: $e);
        }

        return $user;
    }

    private function resolveDuplicateSocialLogin(AuthProvider $provider, string $subject): ?User
    {
        $identity = AuthIdentity::query()
            ->where('provider', $provider->value)
            ->where('provider_subject', $subject)
            ->first();

        if ($identity !== null) {
            $user = User::query()->find($identity->user_id);
            if ($user !== null && ! $user->trashed()) {
                return $user;
            }
        }

        $legacyByProvider = $this->findLegacyUserByProviderId($provider, $subject);
        if ($legacyByProvider !== null && ! $legacyByProvider->trashed()) {
            return $legacyByProvider;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function providerLinkUpdates(AuthProvider $provider, string $subject): array
    {
        $providerColumn = $this->providerColumn($provider);

        return $providerColumn !== null ? [$providerColumn => $subject] : [];
    }

    private function providerColumn(AuthProvider $provider): ?string
    {
        return match ($provider) {
            AuthProvider::Google => 'google_id',
            AuthProvider::Apple => 'apple_id',
            default => null,
        };
    }
}
