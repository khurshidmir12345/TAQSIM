<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
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

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/auth/account');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
