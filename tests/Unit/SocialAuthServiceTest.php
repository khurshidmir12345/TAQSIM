<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_google_returns_same_active_user_by_provider_id(): void
    {
        $user = User::factory()->create([
            'email' => 'user@gmail.com',
            'google_id' => 'google-sub-123',
        ]);

        $service = app(SocialAuthService::class);
        $resolved = $service->loginWithGoogle(
            'google-sub-123',
            'user@gmail.com',
            'Google User',
            true,
        );

        $this->assertSame($user->id, $resolved->id);
    }

    public function test_login_with_google_creates_new_user_for_trashed_legacy(): void
    {
        $legacy = User::factory()->create([
            'email' => 'user@gmail.com',
            'google_id' => 'google-sub-123',
        ]);
        $legacy->delete();

        $service = app(SocialAuthService::class);
        $resolved = $service->loginWithGoogle(
            'google-sub-123',
            'user@gmail.com',
            'Google User',
            true,
        );

        $this->assertNotSame($legacy->id, $resolved->id);
        $this->assertSame('google-sub-123', $resolved->google_id);
        $this->assertSoftDeleted('users', ['id' => $legacy->id]);
    }

    public function test_login_with_google_does_not_link_unverified_profile_email(): void
    {
        $victim = User::factory()->create([
            'email' => 'victim@gmail.com',
            'email_verified_at' => null,
            'google_id' => null,
        ]);

        $service = app(SocialAuthService::class);
        $resolved = $service->loginWithGoogle(
            'google-sub-new',
            'victim@gmail.com',
            'Attacker',
            true,
        );

        $this->assertNotSame($victim->id, $resolved->id);
        $this->assertSame('google-sub-new', $resolved->google_id);
        $this->assertNull($resolved->email);
        $this->assertNull($victim->fresh()->google_id);
    }

    public function test_login_with_google_does_not_link_when_email_not_verified_in_token(): void
    {
        $victim = User::factory()->create([
            'email' => 'victim@gmail.com',
            'email_verified_at' => null,
        ]);

        $service = app(SocialAuthService::class);
        $resolved = $service->loginWithGoogle(
            'google-sub-new',
            'victim@gmail.com',
            'Attacker',
            false,
        );

        $this->assertNotSame($victim->id, $resolved->id);
        $this->assertNull($resolved->email);
    }

    public function test_login_with_google_links_verified_profile_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'user@gmail.com',
            'email_verified_at' => now(),
            'google_id' => null,
        ]);

        $service = app(SocialAuthService::class);
        $resolved = $service->loginWithGoogle(
            'google-sub-123',
            'user@gmail.com',
            'Google User',
            true,
        );

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame('google-sub-123', $resolved->google_id);
    }
}
