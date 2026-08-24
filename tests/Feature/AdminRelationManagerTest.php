<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Filament\Resources\ShopResource\RelationManagers\UsersRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ShopsRelationManager;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin paneldagi "Do'konlar" va "Foydalanuvchilar" yorliqlari.
 *
 * Ular ochilmasdi: `pivot.user_type` enum bo'lib keladi, ustun closure'i esa
 * `string` deb e'lon qilingan edi va TypeError berardi.
 */
class AdminRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.email' => 'admin@taqseem.uz']);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@taqseem.uz',
            'phone' => '+998900000000',
            'password' => 'secret123',
            'is_accepted_policy' => true,
        ]);
        $this->actingAs($admin);

        $this->owner = User::factory()->create(['name' => 'Egasi']);
        $this->shop = Shop::create([
            'name' => 'Nonxonam',
            'slug' => 'nonxonam-' . Str::random(5),
            'is_active' => true,
        ]);
        $this->owner->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Owner,
        ]);
    }

    public function test_user_shops_tab_renders_the_role(): void
    {
        Livewire::test(ShopsRelationManager::class, [
            'ownerRecord' => $this->owner,
            'pageClass' => \App\Filament\Resources\UserResource\Pages\ViewUser::class,
        ])
            ->assertOk()
            ->assertSee('Nonxonam')
            ->assertSee('Egasi');
    }

    public function test_shop_users_tab_renders_the_role(): void
    {
        $seller = User::factory()->create(['name' => 'Xodim']);
        $seller->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
        ]);

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->shop,
            'pageClass' => \App\Filament\Resources\ShopResource\Pages\ViewShop::class,
        ])
            ->assertOk()
            ->assertSee('Egasi')
            ->assertSee('Sotuvchi');
    }

    public function test_enum_helpers(): void
    {
        $this->assertSame('Egasi', ShopUserType::Owner->label());
        $this->assertSame('Sotuvchi', ShopUserType::Seller->label());
        $this->assertSame(ShopUserType::Owner, ShopUserType::resolve('owner'));
        $this->assertSame(ShopUserType::Seller, ShopUserType::resolve(ShopUserType::Seller));
        $this->assertNull(ShopUserType::resolve('nomalum'));
        $this->assertNull(ShopUserType::resolve(null));
    }
}
