<?php

namespace Tests\Feature;

use App\Enums\AuthProvider;
use App\Models\AuthIdentity;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_confirm_deletes_account_with_anonymization_and_frees_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '+998901111111',
            'email' => 'web-user@example.com',
            'google_id' => 'google-sub-web',
            'telegram_chat_id' => 123456789,
        ]);

        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => AuthProvider::Google,
            'provider_subject' => 'google-sub-web',
            'verified_at' => now(),
        ]);

        PhoneVerificationCode::create([
            'phone' => '+998901111111',
            'code' => '4321',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/delete-account/confirm', [
            'phone' => '+998901111111',
            'code' => '4321',
            'agree' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Akkaunt va unga bog\'liq barcha ma\'lumotlar o\'chirildi.',
            ]);

        $user->refresh();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotSame('+998901111111', $user->phone);
        $this->assertNull($user->email);
        $this->assertNull($user->google_id);
        $this->assertNull($user->telegram_chat_id);
        $this->assertDatabaseMissing('auth_identities', [
            'user_id' => $user->id,
        ]);

        PhoneVerificationCode::create([
            'phone' => '+998901111111',
            'code' => '5678',
            'expires_at' => now()->addMinutes(2),
        ]);

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'phone' => '+998901111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'code' => '5678',
        ]);

        $registerResponse->assertStatus(201)
            ->assertJsonPath('data.user.phone', '+998901111111');

        $this->assertNotSame($user->id, $registerResponse->json('data.user.id'));
    }
}
