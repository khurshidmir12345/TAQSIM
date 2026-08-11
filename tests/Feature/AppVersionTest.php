<?php

namespace Tests\Feature;

use App\Services\AppUpdateService;
use Tests\TestCase;

/**
 * `.env` dagi versiya bilan ilova versiyasini solishtirish.
 */
class AppVersionTest extends TestCase
{
    private function configure(bool $enabled, string $androidVersion, string $iosVersion = ''): void
    {
        config([
            'app_update.enabled' => $enabled,
            'app_update.android.version' => $androidVersion,
            'app_update.android.url' => 'https://play.google.com/store/apps/details?id=uz.taqseem.mobile',
            'app_update.ios.version' => $iosVersion,
            'app_update.ios.url' => '',
        ]);
    }

    public function test_older_app_version_gets_an_update(): void
    {
        $this->configure(true, '1.2.8');

        $this->getJson('/api/v1/app-version?platform=android&version=1.2.7')
            ->assertOk()
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.latest_version', '1.2.8')
            ->assertJsonPath('data.store_url', 'https://play.google.com/store/apps/details?id=uz.taqseem.mobile');
    }

    public function test_same_or_newer_app_version_gets_nothing(): void
    {
        $this->configure(true, '1.2.8');

        $this->getJson('/api/v1/app-version?platform=android&version=1.2.8')
            ->assertOk()
            ->assertJsonPath('data.update_available', false);

        // Test builds do'kondagidan oldinda bo'lishi mumkin.
        $this->getJson('/api/v1/app-version?platform=android&version=1.3.0')
            ->assertOk()
            ->assertJsonPath('data.update_available', false);
    }

    public function test_disabled_flag_silences_the_modal(): void
    {
        $this->configure(false, '9.9.9');

        $this->getJson('/api/v1/app-version?platform=android&version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.update_available', false);
    }

    public function test_empty_version_disables_the_check_for_that_platform(): void
    {
        // Android to'ldirilgan, iOS bo'sh — App Store tekshiruvi kechikkan holat.
        $this->configure(true, '1.2.8', '');

        $this->getJson('/api/v1/app-version?platform=ios&version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.update_available', false)
            ->assertJsonPath('data.latest_version', null);

        $this->getJson('/api/v1/app-version?platform=android&version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.update_available', true);
    }

    public function test_missing_client_version_is_not_an_update(): void
    {
        $this->configure(true, '1.2.8');

        $this->getJson('/api/v1/app-version?platform=android')
            ->assertOk()
            ->assertJsonPath('data.update_available', false);
    }

    public function test_unknown_platform_is_rejected(): void
    {
        $this->getJson('/api/v1/app-version?platform=windows&version=1.0.0')
            ->assertStatus(422);
    }

    public function test_version_comparison_handles_build_numbers_and_segments(): void
    {
        $this->assertLessThan(0, AppUpdateService::compare('1.2.7', '1.2.8'));
        $this->assertLessThan(0, AppUpdateService::compare('1.2.7', '1.10.0'));
        $this->assertLessThan(0, AppUpdateService::compare('1.2', '1.2.1'));
        $this->assertSame(0, AppUpdateService::compare('1.2.7', '1.2.7'));
        $this->assertGreaterThan(0, AppUpdateService::compare('2.0.0', '1.9.9'));

        // Build raqami faqat semantik qismlar teng bo'lganda hal qiladi.
        $this->assertLessThan(0, AppUpdateService::compare('1.2.7+39', '1.2.7+40'));
        $this->assertSame(0, AppUpdateService::compare('1.2.7+39', '1.2.7+39'));
        $this->assertGreaterThan(0, AppUpdateService::compare('1.2.8+1', '1.2.7+99'));
    }
}
