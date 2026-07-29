<?php

namespace Tests\Feature;

use App\Exceptions\SocialAuthConflictException;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\AppleAuthService;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AppleAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string,mixed>  $payload
     */
    private function mockAppleService(array $payload): void
    {
        $this->mock(AppleAuthService::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('verifyIdentityToken')->andReturn(array_merge([
                'sub' => 'apple-sub-123',
                'email' => 'user@icloud.com',
                'email_verified' => true,
                'raw' => [],
            ], $payload));
        });
    }

    public function test_new_user_can_sign_in_with_apple(): void
    {
        $this->mockAppleService([]);

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'fake.jwt.token',
            'name' => 'Apple User',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'user@icloud.com');

        $this->assertDatabaseHas('users', [
            'email' => 'user@icloud.com',
            'apple_id' => 'apple-sub-123',
        ]);
    }

    public function test_apple_without_token_email_does_not_link_request_email(): void
    {
        $this->mockAppleService([
            'email' => null,
            'email_verified' => false,
        ]);

        $victim = User::factory()->create([
            'email' => 'victim@icloud.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'fake.jwt.token',
            'email' => 'victim@icloud.com',
            'name' => 'Attacker',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($victim->id, $newUserId);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'apple_id' => 'apple-sub-123',
            'email' => null,
        ]);
    }

    public function test_apple_without_token_email_logs_in_via_existing_identity(): void
    {
        $this->mockAppleService([
            'email' => null,
            'email_verified' => false,
        ]);

        $user = User::factory()->create([
            'email' => 'user@icloud.com',
            'apple_id' => 'apple-sub-123',
        ]);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_subject' => 'apple-sub-123',
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'fake.jwt.token',
            'email' => 'someone-else@icloud.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_verified_apple_email_does_not_link_unverified_profile(): void
    {
        $this->mockAppleService([]);

        $victim = User::factory()->create([
            'email' => 'user@icloud.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'fake.jwt.token',
            'name' => 'Apple User',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($victim->id, $newUserId);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'apple_id' => 'apple-sub-123',
            'email' => null,
        ]);
    }

    public function test_soft_deleted_apple_identity_creates_new_user(): void
    {
        $this->mockAppleService([]);

        $user = User::factory()->create([
            'email' => 'user@icloud.com',
            'apple_id' => 'apple-sub-123',
        ]);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_subject' => 'apple-sub-123',
            'verified_at' => now(),
        ]);
        $user->createToken('legacy-device');
        $user->delete();

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'fake.jwt.token',
            'name' => 'Apple User',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($user->id, $newUserId);

        $user->refresh();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull($user->apple_id);
        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'apple_id' => 'apple-sub-123',
            'deleted_at' => null,
        ]);
    }

    public function test_social_auth_conflict_returns_409(): void
    {
        $this->mockAppleService([]);

        $this->mock(SocialAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('loginWithApple')
                ->once()
                ->andThrow(new SocialAuthConflictException('Social login conflict.'));
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'fake.jwt.token',
        ]);

        $response->assertStatus(409)
            ->assertJson(['success' => false])
            ->assertJsonPath('errors.code', 'social_auth_conflict');
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->mock(AppleAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyIdentityToken')->andThrow(new RuntimeException('invalid'));
        });

        $response = $this->postJson('/api/v1/auth/apple', [
            'identity_token' => 'bad.token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
