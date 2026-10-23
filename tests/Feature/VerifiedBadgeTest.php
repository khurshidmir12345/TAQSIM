<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Profildagi "Tasdiqlangan hisob" belgisi faqat pullik va muddati tugamagan
 * foydalanuvchiga chiqadi. Ilovaga tarif yoki muddat uzatilmaydi.
 */
class VerifiedBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function me(User $user)
    {
        return $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_paid_user_is_verified(): void
    {
        $user = User::factory()->create(['access_source' => 'paid', 'access_until' => now()->addMonth()]);

        $this->me($user)
            ->assertJsonPath('data.user.is_verified', true)
            ->assertJsonMissingPath('data.user.access_until')
            ->assertJsonMissingPath('data.user.access_source');
    }

    public function test_trial_user_is_not_verified(): void
    {
        $user = User::factory()->create(['access_source' => 'trial', 'access_until' => now()->addMonth()]);

        $this->me($user)->assertJsonPath('data.user.is_verified', false);
    }

    public function test_expired_paid_user_is_not_verified(): void
    {
        $user = User::factory()->create(['access_source' => 'paid', 'access_until' => now()->subDay()]);

        $this->me($user)->assertJsonPath('data.user.is_verified', false);
    }

    public function test_badge_can_be_switched_off(): void
    {
        config()->set('access.verified_badge', false);
        $user = User::factory()->create(['access_source' => 'paid', 'access_until' => now()->addMonth()]);

        $this->me($user)->assertJsonPath('data.user.is_verified', false);
    }
}
