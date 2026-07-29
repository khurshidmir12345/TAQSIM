<?php

namespace Tests\Feature;

use App\Enums\AuthProvider;
use App\Models\AuthIdentity;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        // Ro'yxatdan o'tish OTP talab qiladi — avval kod yaratamiz.
        PhoneVerificationCode::create([
            'phone' => '+998901111111',
            'code' => '1234',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'code' => '1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'phone'],
                    'token',
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['phone' => '+998901111111']);
    }

    public function test_register_succeeds_after_soft_deleted_phone(): void
    {
        $deletedUser = User::factory()->create([
            'phone' => '+998901111111',
            'name' => 'Deleted User',
            'email' => 'deleted@example.com',
            'google_id' => 'google-sub-old',
            'telegram_chat_id' => 123456789,
        ]);

        AuthIdentity::create([
            'user_id' => $deletedUser->id,
            'provider' => AuthProvider::Google,
            'provider_subject' => 'google-sub-old',
            'verified_at' => now(),
        ]);

        $deletedUser->createToken('legacy-device');
        $deletedUser->delete();

        PhoneVerificationCode::create([
            'phone' => '+998901111111',
            'code' => '1234',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'code' => '1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.name', 'New User')
            ->assertJsonPath('data.user.phone', '+998901111111');

        $newUserId = $response->json('data.user.id');
        $this->assertNotSame($deletedUser->id, $newUserId);

        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'phone' => '+998901111111',
            'name' => 'New User',
        ]);

        $deletedUser->refresh();
        $this->assertSoftDeleted('users', ['id' => $deletedUser->id]);
        $this->assertNotSame('+998901111111', $deletedUser->phone);
        $this->assertNull($deletedUser->email);
        $this->assertNull($deletedUser->google_id);
        $this->assertNull($deletedUser->telegram_chat_id);
        $this->assertDatabaseMissing('auth_identities', [
            'user_id' => $deletedUser->id,
        ]);
        $this->assertSame(0, $deletedUser->tokens()->count());
    }

    public function test_delete_then_register_full_flow(): void
    {
        $user = User::factory()->create([
            'phone' => '+998901111111',
            'name' => 'Old User',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/auth/account')
            ->assertOk();

        PhoneVerificationCode::create([
            'phone' => '+998901111111',
            'code' => '5678',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Fresh User',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'code' => '5678',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.name', 'Fresh User')
            ->assertJsonPath('data.user.phone', '+998901111111');

        $this->assertNotSame($user->id, $response->json('data.user.id'));
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_delete_account_removes_local_avatar_after_commit(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/test.jpg', 'avatar-bytes');

        $user = User::factory()->create([
            'avatar_url' => 'avatars/test.jpg',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/auth/account')
            ->assertOk();

        Storage::disk('public')->assertMissing('avatars/test.jpg');
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['name', 'phone', 'password']);
    }

    public function test_register_validates_unique_phone(): void
    {
        User::factory()->create(['phone' => '+998901111111']);

        PhoneVerificationCode::create([
            'phone' => '+998901111111',
            'code' => '1234',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'code' => '1234',
        ]);

        // Telefon band bo'lsa controller 409 + phone_exists qaytaradi.
        $response->assertStatus(409)
            ->assertJson(['success' => false])
            ->assertJsonPath('errors.phone_exists', true);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'phone' => '+998901111111',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901111111',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'phone'],
                    'token',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+998901111111',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901111111',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->create([
            'phone' => '+998901111111',
            'password' => 'password123',
            'blocked_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901111111',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false])
            ->assertJsonPath('errors.code', 'account_blocked');
    }

    public function test_blocked_user_is_denied_on_protected_routes(): void
    {
        $user = User::factory()->create(['blocked_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'account_blocked']);
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/auth/profile', [
                'name' => 'Yangi Ism',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.name', 'Yangi Ism');
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'oldpassword']);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'oldpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create([
            'phone' => '+998901111111',
            'email' => 'user@example.com',
            'google_id' => 'google-sub-123',
            'telegram_chat_id' => 987654321,
        ]);

        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => AuthProvider::Google,
            'provider_subject' => 'google-sub-123',
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/auth/account');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotSame('+998901111111', $user->phone);
        $this->assertNull($user->email);
        $this->assertNull($user->google_id);
        $this->assertNull($user->telegram_chat_id);
        $this->assertDatabaseMissing('auth_identities', [
            'user_id' => $user->id,
        ]);
    }
}
