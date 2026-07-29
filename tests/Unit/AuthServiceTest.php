<?php

namespace Tests\Unit;

use App\Exceptions\ActiveUserPurgeForbiddenException;
use App\Exceptions\InvalidRegistrationCodeException;
use App\Exceptions\PhoneAlreadyRegisteredException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_register_throws_for_invalid_code_inside_transaction(): void
    {
        $otpService = Mockery::mock(OtpService::class);
        $otpService->shouldReceive('validate')
            ->once()
            ->with('+998901111111', '9999')
            ->andReturn(null);

        $service = new AuthService($otpService);

        $this->expectException(InvalidRegistrationCodeException::class);

        $service->register('+998901111111', '9999', 'Test User', 'password123');
    }

    public function test_register_throws_for_active_phone(): void
    {
        User::factory()->create(['phone' => '+998901111111']);

        $otpService = Mockery::mock(OtpService::class);
        $otpService->shouldNotReceive('validate');

        $service = new AuthService($otpService);

        $this->expectException(PhoneAlreadyRegisteredException::class);

        $service->register('+998901111111', '1234', 'Test User', 'password123');
    }

    public function test_delete_account_anonymizes_identifiers(): void
    {
        $user = User::factory()->create([
            'phone' => '+998901111111',
            'email' => 'delete-me@example.com',
            'google_id' => 'google-sub-delete',
        ]);

        $service = new AuthService(app(OtpService::class));
        $service->deleteAccount($user);

        $user->refresh();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotSame('+998901111111', $user->phone);
        $this->assertStringStartsWith('d', $user->phone);
        $this->assertNull($user->email);
        $this->assertNull($user->google_id);
    }

    public function test_purge_legacy_account_revokes_tokens_for_trashed_user(): void
    {
        $user = User::factory()->create(['phone' => '+998901111111']);
        $user->createToken('mobile');
        $user->delete();

        $service = new AuthService(app(OtpService::class));
        $service->purgeLegacyAccount($user);

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
        $this->assertDatabaseMissing('auth_identities', ['user_id' => $user->id]);
    }

    public function test_purge_legacy_account_rejects_active_user(): void
    {
        $user = User::factory()->create(['phone' => '+998901111111']);

        $service = new AuthService(app(OtpService::class));

        $this->expectException(ActiveUserPurgeForbiddenException::class);

        $service->purgeLegacyAccount($user);
    }

    public function test_purge_legacy_avatar_deleted_after_outer_transaction_commits(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/legacy.jpg', 'legacy');

        $user = User::factory()->create([
            'phone' => '+998901111111',
            'avatar_url' => 'avatars/legacy.jpg',
        ]);
        $user->delete();

        $service = new AuthService(app(OtpService::class));

        DB::transaction(function () use ($service, $user) {
            $service->purgeLegacyAccount($user);
        });

        Storage::disk('public')->assertMissing('avatars/legacy.jpg');
    }

    public function test_purge_legacy_avatar_preserved_when_outer_transaction_rolls_back(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/legacy.jpg', 'legacy');

        $user = User::factory()->create([
            'phone' => '+998901111111',
            'avatar_url' => 'avatars/legacy.jpg',
        ]);
        $user->delete();

        $service = new AuthService(app(OtpService::class));

        try {
            DB::transaction(function () use ($service, $user) {
                $service->purgeLegacyAccount($user);
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('public')->assertExists('avatars/legacy.jpg');
    }

    public function test_delete_account_deletes_avatar_after_commit(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/active.jpg', 'active');

        $user = User::factory()->create([
            'avatar_url' => 'avatars/active.jpg',
        ]);

        $service = new AuthService(app(OtpService::class));
        $service->deleteAccount($user);

        Storage::disk('public')->assertMissing('avatars/active.jpg');
    }
}
