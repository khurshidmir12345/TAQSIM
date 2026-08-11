<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parolni unutgan foydalanuvchi: SMS kodi bilan yangi parol o'rnatish.
 */
class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+998901234567';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Khurshid',
            'phone' => self::PHONE,
            'password' => 'eski-parol',
            'is_accepted_policy' => true,
            'phone_verified_at' => now(),
        ]);
    }

    private function sendCode(?string $phone = null): string
    {
        $this->postJson('/api/v1/auth/send-code', ['phone' => $phone ?? self::PHONE])
            ->assertOk();

        return PhoneVerificationCode::where('phone', $phone ?? self::PHONE)
            ->latest()
            ->firstOrFail()
            ->code;
    }

    public function test_password_is_reset_and_user_is_logged_in(): void
    {
        $code = $this->sendCode();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => 'yangi-parol',
            'password_confirmation' => 'yangi-parol',
        ])->assertOk();

        // Foydalanuvchi darhol ilovaga kiradi — qayta login qilishi shart emas.
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertTrue(Hash::check('yangi-parol', $this->user->fresh()->password));
    }

    public function test_new_password_works_for_login(): void
    {
        $code = $this->sendCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => 'yangi-parol',
            'password_confirmation' => 'yangi-parol',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'phone' => self::PHONE,
            'password' => 'yangi-parol',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'phone' => self::PHONE,
            'password' => 'eski-parol',
        ])->assertStatus(422);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->sendCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => '0000',
            'password' => 'yangi-parol',
            'password_confirmation' => 'yangi-parol',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('eski-parol', $this->user->fresh()->password));
    }

    public function test_code_cannot_be_reused(): void
    {
        $code = $this->sendCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => 'birinchi-parol',
            'password_confirmation' => 'birinchi-parol',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => 'ikkinchi-parol',
            'password_confirmation' => 'ikkinchi-parol',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('birinchi-parol', $this->user->fresh()->password));
    }

    /** Ro'yxatdan o'tmagan raqam borligini oshkor qilmaslik kerak. */
    public function test_unknown_phone_returns_the_same_error(): void
    {
        $other = '+998900000000';
        $code = $this->sendCode($other);

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => $other,
            'code' => $code,
            'password' => 'yangi-parol',
            'password_confirmation' => 'yangi-parol',
        ])->assertStatus(422);
    }

    public function test_password_confirmation_must_match(): void
    {
        $code = $this->sendCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => 'yangi-parol',
            'password_confirmation' => 'boshqa-parol',
        ])->assertStatus(422);
    }

    public function test_short_password_is_rejected(): void
    {
        $code = $this->sendCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertStatus(422);
    }

    public function test_blocked_account_cannot_reset(): void
    {
        $this->user->update(['blocked_at' => now()]);
        $code = $this->sendCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => self::PHONE,
            'code' => $code,
            'password' => 'yangi-parol',
            'password_confirmation' => 'yangi-parol',
        ])->assertStatus(403);
    }
}
