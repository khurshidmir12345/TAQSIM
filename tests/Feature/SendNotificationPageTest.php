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

    public function test_targeted_send_passes_only_selected_users(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        $a = User::create([
            'name' => 'Ali',
            'phone' => '+998901111111',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);
        User::create([
            'name' => 'Vali',
            'phone' => '+998902222222',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        Livewire::test(SendNotification::class)
            ->fillForm([
                'target' => 'user',
                'user_ids' => [$a->id],
                'title' => 'Shaxsiy',
                'body' => 'Faqat sizga',
            ])
            ->callAction('send')
            ->assertHasNoFormErrors();

        Queue::assertPushed(
            SendBulkNotification::class,
            function (SendBulkNotification $job) use ($a): bool {
                // Konstruktor argumentlari private — refleksiya bilan tekshiramiz.
                $ref = new \ReflectionProperty($job, 'userIds');

                return $ref->getValue($job) === [$a->id];
            },
        );
    }

    public function test_user_list_is_available_as_options(): void
    {
        $this->actingAsAdmin();

        $ali = User::create([
            'name' => 'Ali',
            'phone' => '+998903333333',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);

        // Bloklangan foydalanuvchi ro'yxatda bo'lmasligi kerak.
        $blocked = User::create([
            'name' => 'Bloklangan',
            'phone' => '+998904444444',
            'password' => 'secret123',
            'is_accepted_policy' => true,
            'blocked_at' => now(),
        ]);

        Livewire::test(SendNotification::class)
            ->fillForm(['target' => 'user'])
            ->assertFormFieldExists('user_ids', function ($field) use ($ali, $blocked): bool {
                $options = $field->getOptions();

                return array_key_exists($ali->id, $options)
                    && ! array_key_exists($blocked->id, $options);
            });
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
