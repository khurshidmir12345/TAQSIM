<?php

namespace App\Models;

use App\Enums\ShopUserType;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'telegram_chat_id',
        'telegram_username',
        'google_id',
        'apple_id',
        'is_accepted_policy',
        'avatar_url',
        'locale',
        'notification_prefs',
        'blocked_at',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
            'telegram_chat_id' => 'integer',
            'is_accepted_policy' => 'boolean',
            'notification_prefs' => 'array',
        ];
    }

    /** Foydalanuvchi admin tomonidan bloklanganmi. */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function authIdentities(): HasMany
    {
        return $this->hasMany(AuthIdentity::class);
    }

    public function userShops(): HasMany
    {
        return $this->hasMany(UserShop::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'user_shops')
            ->using(UserShop::class)
            ->withPivot('user_type', 'permissions')
            ->withTimestamps();
    }

    public function ownedShops(): BelongsToMany
    {
        return $this->shops()->wherePivot('user_type', ShopUserType::Owner->value);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $adminEmail = config('admin.email');

        return ! empty($adminEmail) && $this->email === $adminEmail;
    }

    // ─── Rol (user_type) ─────────────────────────────────────────────────────

    private ?string $cachedUserType = null;

    public function isShopOwner(): bool
    {
        return $this->ownedShops()->exists();
    }

    public function isSeller(): bool
    {
        return $this->userShops()
            ->where('user_type', ShopUserType::Seller->value)
            ->exists();
    }

    /**
     * Global rol: do'kon egasi bo'lsa 'owner', xodim bo'lsa 'seller',
     * aks holda (hali do'kon yo'q yangi user) — 'owner' (prospektiv egasi).
     * Har bir API javobida shu qaytariladi (so'rov davomida keshlanadi).
     */
    public function globalUserType(): string
    {
        if ($this->cachedUserType !== null) {
            return $this->cachedUserType;
        }

        if ($this->isSeller() && ! $this->isShopOwner()) {
            return $this->cachedUserType = ShopUserType::Seller->value;
        }

        return $this->cachedUserType = ShopUserType::Owner->value;
    }
}
