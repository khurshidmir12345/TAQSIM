<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parolni unutgan foydalanuvchi: kod bilan kiradi, parolni keyin ilova ichida
 * qo'yadi.
 *
 * Ilgari parol kod bilan bir so'rovda o'rnatilardi va foydalanuvchi ikki marta
 * parol yozguncha kodning 2 daqiqasi o'tib ketardi.
 */
class LoginWithCodeTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+998901234567';

    private function makeUser(?string $password = 'eski123'): User
    {
        return User::create([
            'name' => 'Test',
            'phone' => self::PHONE,
            'password' => $password,
            'is_accepted_policy' => true,
            'phone_verified_at' => now(),
        ]);
    }

    private function makeCode(string $code = '1234'): void
    {
        PhoneVerificationCode::create([
            'phone' => self::PHONE,
            'code' => $code,
            'expires_at' => now()->addMinutes(2),
        ]);
    }

    // ─── Kod bilan kirish ────────────────────────────────────────────────

    public function test_valid_code_logs_user_in_without_password(): void
    {
        $user = $this->makeUser();
        $this->makeCode();

        $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => self::PHONE,
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        // Parol o'zgarmagan bo'lishi kerak — bu qadam faqat kirish.
        $this->assertTrue(Hash::check('eski123', $user->fresh()->password));
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->makeUser();
        $this->makeCode();

        $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => self::PHONE,
            'code' => '9999',
        ])->assertStatus(422)->assertJsonPath('errors.code.0', __('api.auth.invalid_code'));
    }

    public function test_unknown_phone_gives_same_error(): void
    {
        // Raqam ro'yxatdan o'tmaganini oshkor qilmaymiz.
        $this->makeCode();

        $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => '+998900000000',
            'code' => '1234',
        ])->assertStatus(422);
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        $user = $this->makeUser();
        $user->update(['blocked_at' => now()]);
        $this->makeCode();

        $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => self::PHONE,
            'code' => '1234',
        ])->assertStatus(403);
    }

    // ─── Parolni keyin qo'yish ───────────────────────────────────────────

    public function test_after_code_login_password_can_be_set_without_the_old_one(): void
    {
        $this->makeUser();
        $this->makeCode();

        $token = $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => self::PHONE,
            'code' => '1234',
        ])->json('data.token');

        // Eski parolsiz — parolni unutgan odam uni bilmaydi.
        $this->withToken($token)
            ->putJson('/api/v1/auth/password', [
                'password' => 'yangi12345',
                'password_confirmation' => 'yangi12345',
            ])
            ->assertOk();

        $this->assertTrue(
            Hash::check('yangi12345', User::where('phone', self::PHONE)->first()->password),
        );
    }

    public function test_pending_state_survives_until_password_is_set(): void
    {
        $user = $this->makeUser();
        $this->makeCode();

        $token = $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => self::PHONE,
            'code' => '1234',
        ])->json('data.token');

        // Foydalanuvchi parol qo'ymay chiqib ketdi — holat saqlanib qolishi
        // kerak, aks holda qaytganda eski parol so'ralib, tiqilib qolardi.
        $this->assertTrue($user->fresh()->mustSetPassword());

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.must_set_password', true);
    }

    public function test_grant_is_single_use(): void
    {
        $user = $this->makeUser();
        $this->makeCode();

        $token = $this->postJson('/api/v1/auth/login-with-code', [
            'phone' => self::PHONE,
            'code' => '1234',
        ])->json('data.token');

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'password' => 'yangi12345',
            'password_confirmation' => 'yangi12345',
        ])->assertOk();

        // Parol qo'yilgach holat tozalanadi — eski parol yana majburiy.
        $this->assertFalse($user->fresh()->mustSetPassword());
    }

    public function test_normal_session_still_requires_current_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'password' => 'yangi12345',
                'password_confirmation' => 'yangi12345',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_user_without_password_can_set_one(): void
    {
        // Google/Telegram orqali kirgan foydalanuvchida parol yo'q — eski
        // parol so'ralishi mantiqsiz va ularni butunlay to'sib qo'yardi.
        $user = $this->makeUser(null);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'password' => 'yangi12345',
                'password_confirmation' => 'yangi12345',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('yangi12345', $user->fresh()->password));
    }
}
