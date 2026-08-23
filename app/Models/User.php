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
        'must_set_password_at',
        'blocked_at',
        'access_until',
        'access_source',
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
            'must_set_password_at' => 'datetime',
            'access_until' => 'datetime',
        ];
    }

    /**
     * Yangi foydalanuvchiga sinov muddatini beradi.
     *
     * Bitta joyda: ro'yxatdan o'tish, SMS kod bilan kirish, Google, Apple,
     * Telegram va xodim taklifi — hammasi `User::create()` orqali o'tadi.
     * Qiymat oldindan berilgan bo'lsa (testlar, seeder) tegilmaydi.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->access_until === null) {
                $user->access_until = now()->addDays((int) config('access.trial_days', 30));
                $user->access_source ??= 'trial';
            }
        });
    }

    /**
     * Pullik bo'limlar shu foydalanuvchi uchun ochiqmi.
     *
     * Cheklov `.env` dan o'chirilgan bo'lsa hamma narsa ochiq — bu deploysiz
     * to'xtatish tugmasi (`config/access.php`).
     */
    public function hasFullAccess(): bool
    {
        if (! config('access.enabled')) {
            return true;
        }

        return $this->access_until !== null && $this->access_until->isFuture();
    }

    /**
     * Hisob holati: 'trial' | 'paid' | 'expired'.
     *
     * Faqat admin paneli va hisobot uchun. Ruxsat qarori `hasFullAccess()`
     * orqali chiqariladi.
     */
    public function accessStatus(): string
    {
        if ($this->access_until === null || $this->access_until->isPast()) {
            return 'expired';
        }

        return $this->access_source === 'paid' ? 'paid' : 'trial';
    }

    /** Muddat tugashiga necha kun qoldi (o'tib ketgan bo'lsa manfiy). */
    public function accessDaysLeft(): ?int
    {
        if ($this->access_until === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->access_until->startOfDay(), false);
    }

    public function accessNotices(): HasMany
    {
        return $this->hasMany(AccessNotice::class);
    }

    /**
     * SMS kodi bilan kirgan, lekin parolni hali qo'ymagan.
     *
     * Shu holatda ilova parol o'rnatish ekranini ko'rsatadi va eski parol
     * so'ralmaydi.
     */
    public function mustSetPassword(): bool
    {
        return $this->must_set_password_at !== null;
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
