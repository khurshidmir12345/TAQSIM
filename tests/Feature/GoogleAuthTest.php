<?php

namespace Tests\Feature;

use App\Exceptions\SocialAuthConflictException;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GoogleAuthService ni soxta payload qaytaradigan qilib bog'laydi —
     * Google JWKS ga real chiqishsiz controller mantiqi sinaladi.
     *
     * @param  array<string,mixed>  $payload
     */
    private function mockGoogleService(array $payload): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('verifyIdToken')->andReturn(array_merge([
                'sub' => 'google-sub-123',
                'email' => 'user@gmail.com',
                'email_verified' => true,
                'name' => 'Google User',
                'picture' => null,
                'raw' => [],
            ], $payload));
        });
    }

    public function test_new_user_can_sign_in_with_google(): void
    {
        $this->mockGoogleService([]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.email', 'user@gmail.com');

        $this->assertDatabaseHas('users', [
            'email' => 'user@gmail.com',
            'google_id' => 'google-sub-123',
        ]);
        $this->assertDatabaseHas('auth_identities', [
            'provider' => 'google',
            'provider_subject' => 'google-sub-123',
        ]);
    }

    public function test_existing_google_identity_returns_same_user(): void
    {
        $this->mockGoogleService([]);

        $user = User::factory()->create(['email' => 'user@gmail.com', 'google_id' => 'google-sub-123']);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-sub-123',
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(1, User::count());
    }

    public function test_existing_email_account_is_linked_to_google_when_profile_email_verified(): void
    {
        $this->mockGoogleService([]);

        $user = User::factory()->create([
            'email' => 'user@gmail.com',
            'email_verified_at' => now(),
            'google_id' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-sub-123',
        ]);
        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_verified_provider_email_does_not_link_unverified_profile(): void
    {
        $this->mockGoogleService([]);

        $victim = User::factory()->create([
            'email' => 'user@gmail.com',
            'email_verified_at' => null,
            'google_id' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($victim->id, $newUserId);
        $this->assertNull($victim->fresh()->google_id);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'google_id' => 'google-sub-123',
            'email' => null,
        ]);
    }

    public function test_request_body_email_is_not_used_for_account_linking(): void
    {
        $this->mockGoogleService([
            'email' => null,
            'email_verified' => false,
        ]);

        $victim = User::factory()->create([
            'email' => 'victim@gmail.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
            'email' => 'victim@gmail.com',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($victim->id, $newUserId);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'google_id' => 'google-sub-123',
            'email' => null,
        ]);
    }

    public function test_email_verified_false_does_not_link_by_email(): void
    {
        $this->mockGoogleService([
            'email' => 'user@gmail.com',
            'email_verified' => false,
        ]);

        $existing = User::factory()->create([
            'email' => 'user@gmail.com',
            'email_verified_at' => now(),
            'google_id' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($existing->id, $newUserId);
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'google_id' => 'google-sub-123',
            'email' => null,
        ]);
        $this->assertNull($existing->fresh()->google_id);
    }

    public function test_soft_deleted_account_creates_new_user_on_sign_in(): void
    {
        $this->mockGoogleService([]);

        $user = User::factory()->create([
            'email' => 'user@gmail.com',
            'google_id' => 'google-sub-123',
        ]);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'google-sub-123',
            'verified_at' => now(),
        ]);
        $user->createToken('legacy-device');
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk();

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($user->id, $newUserId);

        $user->refresh();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull($user->google_id);
        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'google_id' => 'google-sub-123',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $newUserId,
            'provider' => 'google',
            'provider_subject' => 'google-sub-123',
        ]);
        $this->assertSame(2, User::withTrashed()->count());
    }

    public function test_social_auth_conflict_returns_409(): void
    {
        $this->mockGoogleService([]);

        $this->mock(SocialAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('loginWithGoogle')
                ->once()
                ->andThrow(new SocialAuthConflictException('Social login conflict.'));
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertStatus(409)
            ->assertJson(['success' => false])
            ->assertJsonPath('errors.code', 'social_auth_conflict');
    }

    public function test_invalid_token_returns_401(): void
    {
        $this->mock(GoogleAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyIdToken')->andThrow(new RuntimeException('invalid'));
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'bad.token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_id_token_is_required(): void
    {
        $response = $this->postJson('/api/v1/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_token']);
    }
}
