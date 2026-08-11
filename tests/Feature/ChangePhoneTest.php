<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Profildan telefon raqamni almashtirish: yangi raqamga kod → tasdiqlash.
 *
 * Asosiy kafolat: kod tasdiqlanmaguncha eski raqam tasdiqlangan holida
 * qolaveradi.
 */
class ChangePhoneTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PHONE = '+998901234567';

    private const NEW_PHONE = '+998907654321';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Khurshid',
            'phone' => self::OLD_PHONE,
            'password' => 'parol123',
            'is_accepted_policy' => true,
            'phone_verified_at' => now()->subMonth(),
        ]);
    }

    private function requestCode(string $phone = self::NEW_PHONE): string
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/auth/phone/send-code', ['phone' => $phone])
            ->assertOk();

        return PhoneVerificationCode::where('phone', $phone)
            ->latest()
            ->firstOrFail()
            ->code;
    }

    public function test_phone_changes_only_after_the_code_is_confirmed(): void
    {
        $code = $this->requestCode();

        // Kod yuborilgani bilan raqam hali o'zgarmaydi.
        $this->assertSame(self::OLD_PHONE, $this->user->fresh()->phone);

        $this->actingAs($this->user)
            ->postJson('/api/v1/auth/phone', [
                'phone' => self::NEW_PHONE,
                'code' => $code,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', self::NEW_PHONE);

        $user = $this->user->fresh();

        $this->assertSame(self::NEW_PHONE, $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_wrong_code_keeps_the_old_phone_verified(): void
    {
        $this->requestCode();

        $this->actingAs($this->user)
            ->postJson('/api/v1/auth/phone', [
                'phone' => self::NEW_PHONE,
                'code' => '0000',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $user = $this->user->fresh();

        $this->assertSame(self::OLD_PHONE, $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_code_is_not_sent_to_a_number_owned_by_someone_else(): void
    {
        User::create([
            'name' => 'Boshqa',
            'phone' => self::NEW_PHONE,
            'password' => 'parol123',
            'is_accepted_policy' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/auth/phone/send-code', ['phone' => self::NEW_PHONE])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        // Band raqamga SMS ham, kod ham yaratilmasligi kerak.
        $this->assertDatabaseMissing('phone_verification_codes', [
            'phone' => self::NEW_PHONE,
        ]);
    }

    public function test_current_number_cannot_be_re_requested(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/auth/phone/send-code', ['phone' => self::OLD_PHONE])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/auth/phone/send-code', ['phone' => self::NEW_PHONE])
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/phone', [
            'phone' => self::NEW_PHONE,
            'code' => '1234',
        ])->assertUnauthorized();
    }

    public function test_a_code_from_another_phone_cannot_be_reused(): void
    {
        // Boshqa raqam uchun olingan kod bilan yangi raqamni tasdiqlab
        // bo'lmaydi — kod raqamga bog'langan.
        $otherPhone = '+998901112233';
        $this->postJson('/api/v1/auth/send-code', ['phone' => $otherPhone])->assertOk();
        $otherCode = PhoneVerificationCode::where('phone', $otherPhone)->latest()->firstOrFail()->code;

        $this->actingAs($this->user)
            ->postJson('/api/v1/auth/phone', [
                'phone' => self::NEW_PHONE,
                'code' => $otherCode,
            ])
            ->assertStatus(422);

        $this->assertSame(self::OLD_PHONE, $this->user->fresh()->phone);
    }
}
