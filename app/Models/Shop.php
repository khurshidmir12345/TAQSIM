<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'business_type_id',
        'currency_id',
        'name',
        'slug',
        'description',
        'address',
        'phone',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude'  => 'float',
            'longitude' => 'float',
        ];
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function measurementUnits(): BelongsToMany
    {
        return $this->belongsToMany(MeasurementUnit::class, 'shop_measurement_units')
            ->withTimestamps();
    }

    public function customBusinessType(): HasOne
    {
        return $this->hasOne(CustomBusinessType::class);
    }

    public function userShops(): HasMany
    {
        return $this->hasMany(UserShop::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_shops')
            ->using(UserShop::class)
            ->withPivot('user_type')
            ->withTimestamps();
    }

    public function breadCategories(): HasMany
    {
        return $this->hasMany(BreadCategory::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function breadReturns(): HasMany
    {
        return $this->hasMany(BreadReturn::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }
}
