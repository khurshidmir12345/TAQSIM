<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin paneldagi "Kirish muddati" tugmalari.
 *
 * Bu — pul olishning yagona yo'li: mijoz admin bilan bog'lanadi, admin shu
 * tugmani bosadi. Shuning uchun uzaytirish mantiqi aniq bo'lishi kerak.
 */
class AccessAdminActionTest extends TestCase
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

    public function test_list_page_renders_with_the_access_column(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListUsers::class)->assertOk();
    }

    /** Oyna ochilganda forma xatosiz chiziladi (muddat tanlovi, sana maydoni). */
    public function test_grant_dialog_opens(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->mountTableAction('grantAccess', $user)
            ->assertTableActionMounted('grantAccess')
            ->assertHasNoTableActionErrors();
    }

    public function test_granting_a_month_to_an_expired_user_starts_from_today(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create();
        $user->forceFill(['access_until' => now()->subDays(10)])->save();

        Livewire::test(ListUsers::class)
            ->callTableAction('grantAccess', $user, ['duration' => '1_month'])
            ->assertHasNoTableActionErrors();

        $user->refresh();

        $this->assertSame('paid', $user->access_source);
        $this->assertTrue($user->hasFullAccess());
        $this->assertSame(
            now()->addMonth()->toDateString(),
            $user->access_until->toDateString(),
        );
    }

    /**
     * Muddati tugamagan odamga oy qo'shilsa, qolgan kunlari yo'qolmasin —
     * aks holda erta to'lagan mijoz jarima olgan bo'lardi.
     */
    public function test_granting_extends_from_the_existing_end_date(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create();
        $user->forceFill(['access_until' => now()->addDays(10)])->save();

        Livewire::test(ListUsers::class)
            ->callTableAction('grantAccess', $user, ['duration' => '1_month'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            now()->addDays(10)->addMonth()->toDateString(),
            $user->refresh()->access_until->toDateString(),
        );
    }

    /** Oraliq muddatlar ham tanlanadi: admin har xil kelishuvga duch keladi. */
    public function test_granting_three_and_six_months(): void
    {
        $this->actingAsAdmin();

        foreach (['3_months' => 3, '6_months' => 6] as $option => $months) {
            $user = User::factory()->create();
            $user->forceFill(['access_until' => now()->subDay()])->save();

            Livewire::test(ListUsers::class)
                ->callTableAction('grantAccess', $user, ['duration' => $option])
                ->assertHasNoTableActionErrors();

            $this->assertSame(
                now()->addMonths($months)->toDateString(),
                $user->refresh()->access_until->toDateString(),
                "{$option} noto'g'ri hisoblandi",
            );
            $this->assertSame('paid', $user->access_source);
        }
    }

    public function test_granting_a_year(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create();
        $user->forceFill(['access_until' => now()->subDay()])->save();

        Livewire::test(ListUsers::class)
            ->callTableAction('grantAccess', $user, ['duration' => '1_year'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            now()->addYear()->toDateString(),
            $user->refresh()->access_until->toDateString(),
        );
    }

    /**
     * Telegram hisobini uzish.
     *
     * Kerak bo'ladigan holat: bitta Telegram hisobi bir vaqtda faqat bitta
     * foydalanuvchida turadi. Odam eski akkountini tashlab yangisiga o'tsa,
     * eskisidan uzmaguncha yangisiga ulay olmaydi.
     */
    public function test_unlinking_telegram_frees_the_account(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create([
            'telegram_chat_id' => 5557554848,
            'telegram_username' => 'khurshid_mirzajonov',
        ]);

        Livewire::test(ListUsers::class)
            ->callTableAction('unlinkTelegram', $user)
            ->assertHasNoTableActionErrors();

        $user->refresh();

        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_username);
    }

    /** Ulanmagan foydalanuvchida tugma ko'rinmasligi kerak. */
    public function test_unlink_action_is_hidden_without_telegram(): void
    {
        $this->actingAsAdmin();

        $linked = User::factory()->create(['telegram_chat_id' => 111222333]);
        $notLinked = User::factory()->create(['telegram_chat_id' => null]);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('unlinkTelegram', $linked)
            ->assertTableActionHidden('unlinkTelegram', $notLinked);
    }

    /** Telegram ID bo'yicha qidirish: "bu hisob kimga bog'langan?" */
    public function test_users_can_be_found_by_telegram_chat_id(): void
    {
        $this->actingAsAdmin();

        $target = User::factory()->create([
            'name' => 'Eski akkount',
            'telegram_chat_id' => 5557554848,
        ]);
        $other = User::factory()->create(['name' => 'Boshqa odam']);

        Livewire::test(ListUsers::class)
            ->searchTable('5557554848')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_revoking_closes_the_paid_sections_immediately(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create();
        $user->forceFill(['access_until' => now()->addYear()])->save();

        Livewire::test(ListUsers::class)
            ->callTableAction('revokeAccess', $user)
            ->assertHasNoTableActionErrors();

        config()->set('access.enabled', true);

        $this->assertFalse($user->refresh()->hasFullAccess());
        $this->assertSame('expired', $user->accessStatus());
    }

    public function test_custom_date_sets_the_end_of_that_day(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create();
        $target = now()->addDays(45);

        Livewire::test(ListUsers::class)
            ->callTableAction('grantAccess', $user, [
                'duration' => 'custom',
                'access_until' => $target->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $user->refresh();

        $this->assertSame($target->toDateString(), $user->access_until->toDateString());
        $this->assertSame('paid', $user->access_source);
        $this->assertSame('23:59:59', $user->access_until->format('H:i:s'));
    }
}
