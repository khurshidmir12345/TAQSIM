<?php

namespace Tests\Feature;

use App\Filament\Pages\SendNotification;
use App\Jobs\SendBulkNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin paneldagi "Bildirishnoma yuborish" sahifasi.
 *
 * Bu testlar sahifa haqiqatan render bo'lishini tekshiradi — Filament/Livewire
 * blade xatolari (masalan mavjud bo'lmagan metod chaqirilishi) faqat sahifa
 * ochilganda bilinadi, sinf kompilyatsiyasida emas.
 */
class SendNotificationPageTest extends TestCase
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

    public function test_page_renders(): void
    {
        $this->actingAsAdmin();

        Livewire::test(SendNotification::class)
            ->assertOk();
    }

    public function test_broadcast_dispatches_job_for_all_users(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        Livewire::test(SendNotification::class)
            ->fillForm([
                'target' => 'all',
                'title' => 'Umumiy xabar',
                'body' => 'Hammaga',
            ])
            ->callAction('send')
            ->assertHasNoFormErrors();

        Queue::assertPushed(SendBulkNotification::class);
    }

    public function test_title_and_body_are_required(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        Livewire::test(SendNotification::class)
            ->fillForm(['target' => 'all', 'title' => '', 'body' => ''])
            ->callAction('send')
            ->assertHasFormErrors(['title', 'body']);

        Queue::assertNothingPushed();
    }
}
