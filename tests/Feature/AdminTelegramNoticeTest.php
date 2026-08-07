<?php

namespace Tests\Feature;

use App\Jobs\SendAdminTelegramMessage;
use App\Models\Shop;
use App\Models\User;
use App\Services\RegistrationNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin Telegram xabarlari auth oqimini sekinlashtirmasligi kerak.
 *
 * Ilgari xabar so'rov ichida sinxron yuborilardi va Telegram sekinlashsa
 * foydalanuvchi "Ulanish vaqti tugadi" xatosini olardi (mobil ilova 15
 * soniyada taslim bo'ladi, Laravel'ning HTTP standarti esa 30 soniya edi).
 */
class AdminTelegramNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_notice_is_queued_not_sent_inline(): void
    {
        Queue::fake();
        Http::fake();

        app(RegistrationNotifier::class)
            ->notifyOtpRequested('+998901234567', '123456', false);

        Queue::assertPushed(SendAdminTelegramMessage::class);
        // Eng muhimi: so'rov ichida tashqi HTTP chaqiruvi BO'LMASLIGI kerak.
        Http::assertNothingSent();
    }

    public function test_employee_invite_notice_is_queued(): void
    {
        Queue::fake();
        Http::fake();

        $owner = User::create([
            'name' => 'Egasi',
            'phone' => '+998901111111',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        $shop = Shop::create([
            'name' => 'Non uyi',
            'slug' => 'non-uyi-'.Str::random(5),
            'is_active' => true,
        ]);

        app(RegistrationNotifier::class)
            ->notifyEmployeeInvite($owner, $shop, 'Ali', '+998902222222', '654321');

        Queue::assertPushed(SendAdminTelegramMessage::class);
        Http::assertNothingSent();
    }

    public function test_notice_never_breaks_the_caller(): void
    {
        // Navbat ishlamay qolsa ham chaqiruvchi oqim davom etishi shart.
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('queue down'));

        app(RegistrationNotifier::class)
            ->notifyOtpRequested('+998901234567', '123456', true);

        $this->assertTrue(true, 'Istisno tashqariga chiqmadi');
    }
}
