<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Filament\Resources\AppNotificationResource\Pages\ListAppNotifications;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin paneldagi "Yuborilgan bildirishnomalar" ro'yxati.
 */
class AppNotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        config(['admin.email' => 'admin@taqseem.uz']);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@taqseem.uz',
            'phone' => '+998900000000',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_list_page_renders(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListAppNotifications::class)->assertOk();
    }

    public function test_sent_notifications_are_listed(): void
    {
        $admin = $this->actingAsAdmin();

        AppNotification::create([
            'user_id' => $admin->id,
            'category' => NotificationCategory::Admin,
            'title' => 'Umumiy xabar',
            'body' => 'Hammaga',
        ]);

        Livewire::test(ListAppNotifications::class)
            ->assertOk()
            ->assertSee('Umumiy xabar');
    }

    public function test_can_filter_by_category(): void
    {
        $admin = $this->actingAsAdmin();

        AppNotification::create([
            'user_id' => $admin->id,
            'category' => NotificationCategory::Admin,
            'title' => 'Admin xabari',
            'body' => 'Matn',
        ]);

        AppNotification::create([
            'user_id' => $admin->id,
            'category' => NotificationCategory::DailyGreeting,
            'title' => 'Ertalabki tilak',
            'body' => 'Matn',
        ]);

        Livewire::test(ListAppNotifications::class)
            ->filterTable('category', NotificationCategory::Admin->value)
            ->assertSee('Admin xabari')
            ->assertDontSee('Ertalabki tilak');
    }
}
