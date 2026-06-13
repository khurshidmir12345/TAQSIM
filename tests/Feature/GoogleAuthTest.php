<?php

namespace Tests\Feature;

use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\GoogleAuthService;
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

    public function test_existing_email_account_is_linked_to_google(): void
    {
        $this->mockGoogleService([]);

        $user = User::factory()->create(['email' => 'user@gmail.com', 'google_id' => null]);

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

    public function test_soft_deleted_account_is_restored_on_sign_in(): void
    {
        $this->mockGoogleService([]);

        // Avval o'chirilgan (soft-deleted) akkaunt — unique index hali ushlab turibdi.
        $user = User::factory()->create([
            'email' => 'user@gmail.com',
            'google_id' => 'google-sub-123',
        ]);
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'fake.jwt.token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        // Akkaunt tiklandi, dublikat yaratilmadi.
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, User::withTrashed()->count());
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
