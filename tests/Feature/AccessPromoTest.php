<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\SystemBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessPromoTest extends TestCase
{
    use RefreshDatabase;

    private function owner(?int $chatId, string $locale = 'uz'): User
    {
        $user = User::factory()->create([
            'telegram_chat_id' => $chatId,
            'locale' => $locale,
        ]);
        $shop = Shop::create([
            'name' => 'S', 'slug' => 's-' . Str::random(5), 'is_active' => true,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);
        $user->shops()->attach($shop->id, ['user_type' => ShopUserType::Owner]);

        return $user;
    }

    public function test_promo_goes_only_to_owners_with_telegram_and_only_with_send_flag(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        SystemBot::create(['name' => 'Register', 'type' => 'register', 'token' => 'tok', 'username' => 'bot', 'is_active' => true]);

        $this->owner(111, 'uz');
        $this->owner(222, 'ru');
        $this->owner(null);

        $this->artisan('access:promo')->expectsOutputToContain('Qabul qiluvchilar: 2')->assertSuccessful();
        Http::assertNothingSent();

        $this->artisan('access:promo', ['--send' => true])->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['chat_id'] === 111
            && str_contains($r['text'], '150 000') && str_contains($r['text'], '17 000')
            && str_contains($r['text'], '@taqseem_admin_bot') && $r['parse_mode'] === 'HTML');
        Http::assertSent(fn ($r) => $r['chat_id'] === 222 && str_contains($r['text'], 'Premium'));
    }
}
