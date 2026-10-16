<?php

namespace Tests\Feature;

use App\Jobs\SendAdminTelegramMessage;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Yangi foydalanuvchi qaysi yo'l bilan kelsa ham (telefon, Google, Apple,
 * Telegram) admin Telegram guruhiga xabar ketadi. Ilgari faqat SMS kod
 * so'ralganda xabar kelardi — Google/Apple orqali kelganlar ko'rinmasdi.
 */
class NewUserNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+998901234567';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function registrationNotices(): \Illuminate\Support\Collection
    {
        return Queue::pushed(SendAdminTelegramMessage::class)
            ->filter(fn (SendAdminTelegramMessage $job) => str_contains($this->text($job), 'yangi foydalanuvchi'));
    }

    private function text(SendAdminTelegramMessage $job): string
    {
        return (string) (new \ReflectionProperty($job, 'text'))->getValue($job);
    }

    public function test_phone_signup_sends_notice(): void
    {
        PhoneVerificationCode::create(['phone' => self::PHONE, 'code' => '1234', 'expires_at' => now()->addMinutes(2)]);

        $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])->assertOk();

        $notices = $this->registrationNotices();
        $this->assertCount(1, $notices);
        $text = $this->text($notices->first());
        $this->assertStringContainsString('Telefon (SMS kod)', $text);
        $this->assertStringContainsString(self::PHONE, $text);
    }

    public function test_existing_user_login_sends_no_notice(): void
    {
        User::create(['phone' => self::PHONE, 'password' => 'parol1234', 'is_accepted_policy' => true]);
        PhoneVerificationCode::create(['phone' => self::PHONE, 'code' => '1234', 'expires_at' => now()->addMinutes(2)]);

        $this->postJson('/api/v1/auth/verify-code', ['phone' => self::PHONE, 'code' => '1234'])->assertOk();

        $this->assertCount(0, $this->registrationNotices());
    }

    public function test_google_signup_with_email_sends_notice(): void
    {
        app(SocialAuthService::class)->loginWithGoogle('google-sub-1', 'ali@example.com', 'Ali <Vali>', true);

        $notices = $this->registrationNotices();
        $this->assertCount(1, $notices);
        $text = $this->text($notices->first());
        $this->assertStringContainsString('Google', $text);
        $this->assertStringContainsString('ali@example.com', $text);
        // HTML ekranlangan — Telegram xabarni rad etmaydi.
        $this->assertStringContainsString('Ali &lt;Vali&gt;', $text);

        // Ikkinchi kirish — yangi xabar yo'q.
        app(SocialAuthService::class)->loginWithGoogle('google-sub-1', 'ali@example.com', 'Ali', true);
        $this->assertCount(1, $this->registrationNotices());
    }

    public function test_apple_signup_sends_notice(): void
    {
        app(SocialAuthService::class)->loginWithApple('apple-sub-1', 'x@privaterelay.appleid.com', null, true);

        $notices = $this->registrationNotices();
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('Apple', $this->text($notices->first()));
    }
}
