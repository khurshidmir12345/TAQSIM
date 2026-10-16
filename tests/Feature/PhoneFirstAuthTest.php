<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Yangi kirish oqimi: faqat telefon → (parol bo'lsa parol, bo'lmasa SMS kod)
 * → kod tasdiqlansa darhol tizimga kiradi; parol ixtiyoriy, keyin qo'yiladi.
 */
class PhoneFirstAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+998901234567';

    private function makeUser(?string $password = 'parol123'): User
    {
        return User::create([
            'name' => 'Ali',
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

    public function test_check_phone_for_unknown_number(): void
    {
        $this->postJson('/api/v1/auth/check-phone', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('data.exists', false)
            ->assertJsonPath('data.has_password', false);
    }

    public function test_check_phone_for_user_with_password(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/check-phone', ['phone' => self::PHONE])
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.has_password', true);
    }

    public function test_check_phone_for_user_without_password(): void
    {
        $this->makeUser(password: null);

        $this->postJson('/api/v1/auth/check-phone', ['phone' => self::PHONE])
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.has_password', false);
    }

    public function test_new_number_is_registered_by_code_and_logged_in(): void
    {
        $this->makeCode();

        $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.is_new', true)
            ->assertJsonPath('data.user.phone', self::PHONE)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $user = User::where('phone', self::PHONE)->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertTrue((bool) $user->is_accepted_policy);
    }

    public function test_new_user_can_set_password_later_without_old_one(): void
    {
        $this->makeCode();
        $token = $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])
            ->json('data.token');

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', ['password' => 'yangi123', 'password_confirmation' => 'yangi123'])
            ->assertOk();

        $this->assertTrue(Hash::check('yangi123', User::where('phone', self::PHONE)->value('password')));
    }

    public function test_existing_user_logs_in_by_code_and_may_reset_password(): void
    {
        $user = $this->makeUser();
        $this->makeCode();

        $token = $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.is_new', false)
            ->json('data.token');

        $this->assertSame(1, User::where('phone', self::PHONE)->count());
        // Parol o'zgarmagan, lekin endi eski parolsiz yangisini qo'ya oladi.
        $this->assertTrue(Hash::check('parol123', $user->fresh()->password));

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', ['password' => 'yangi123', 'password_confirmation' => 'yangi123'])
            ->assertOk();
        $this->assertTrue(Hash::check('yangi123', $user->fresh()->password));
    }

    public function test_wrong_code_is_rejected_and_creates_nothing(): void
    {
        $this->makeCode('1234');

        $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '9999'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertSame(0, User::count());
        // Urinish hisoblanadi — kodni cheksiz taxmin qilib bo'lmaydi.
        $this->assertSame(1, (int) PhoneVerificationCode::where('phone', self::PHONE)->value('attempts'));
    }

    public function test_blocked_user_cannot_log_in_by_code(): void
    {
        $this->makeUser()->forceFill(['blocked_at' => now()])->save();
        $this->makeCode();

        $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])
            ->assertStatus(403);
    }

    public function test_deleted_account_number_starts_fresh(): void
    {
        $old = $this->makeUser();
        $old->delete();
        $this->makeCode();

        $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.is_new', true);

        $this->assertNotSame($old->id, User::where('phone', self::PHONE)->value('id'));
    }
}
