<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Filament\Resources\ShopResource\Pages\ViewShop;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\Shop;
use App\Models\SystemBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin paneldan foydalanuvchiga (yoki do'kon egasiga) Telegram xabar. */
class AdminDirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.email' => 'admin@taqseem.uz']);
        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@taqseem.uz',
            'phone' => '+998900000000',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]));

        SystemBot::create(['name' => 'Taqseem', 'type' => 'register', 'token' => 'TEST-TOKEN', 'username' => 'taqseem_bot', 'is_active' => true]);

        $this->owner = User::factory()->create(['name' => 'Sodirjon', 'telegram_chat_id' => 555001]);
        $this->shop = Shop::create(['name' => 'Kumush', 'slug' => 'k-' . Str::random(5), 'is_active' => true]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
    }

    /** Http::fake stub'lari ustma-ust qo'shiladi — har test o'zinikini beradi. */
    private function fakeTelegram(int $status = 200): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => $status === 200], $status)]);
    }

    public function test_message_from_user_page_goes_to_register_bot(): void
    {
        $this->fakeTelegram();

        Livewire::test(ViewUser::class, ['record' => $this->owner->getKey()])
            ->callAction('message', data: ['text' => 'Salom <aka> & xush kelibsiz'])
            ->assertHasNoActionErrors()
            ->assertNotified('Xabar yuborildi');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'botTEST-TOKEN/sendMessage')
            && $r['chat_id'] === 555001
            && $r['text'] === 'Salom &lt;aka&gt; &amp; xush kelibsiz');
    }

    public function test_message_from_shop_page_goes_to_owner(): void
    {
        $this->fakeTelegram();

        Livewire::test(ViewShop::class, ['record' => $this->shop->getKey()])
            ->callAction('message', data: ['text' => 'Salom'])
            ->assertNotified('Xabar yuborildi');

        Http::assertSent(fn (Request $r) => $r['chat_id'] === 555001);
    }

    public function test_action_available_in_users_table(): void
    {
        $this->fakeTelegram();

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('message', $this->owner)
            ->callTableAction('message', $this->owner, data: ['text' => 'Salom'])
            ->assertNotified('Xabar yuborildi');
    }

    public function test_hidden_when_user_has_no_telegram(): void
    {
        $this->fakeTelegram();

        $this->owner->forceFill(['telegram_chat_id' => null])->save();

        Livewire::test(ViewUser::class, ['record' => $this->owner->getKey()])
            ->assertActionHidden('message');

        Http::assertNothingSent();
    }

    public function test_reports_telegram_failure(): void
    {
        $this->fakeTelegram(403);

        Livewire::test(ViewUser::class, ['record' => $this->owner->getKey()])
            ->callAction('message', data: ['text' => 'Salom'])
            ->assertNotified('Yuborilmadi');
    }
}
