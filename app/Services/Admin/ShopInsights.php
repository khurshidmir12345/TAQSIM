<?php

namespace App\Services\Admin;

use App\Enums\ShopUserType;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * Admin panel uchun do'kon "tahlili": faollik, hisobiy moliya va
 * sozlamadagi kamchiliklar.
 *
 * Hammasi faqat o'qish; bitta so'rov ichida har bir do'kon uchun bir marta
 * hisoblanadi (infolistdagi o'nlab maydon bitta natijadan o'qiydi).
 */
class ShopInsights
{
    /** Shu kundan ko'p yozuv bo'lmasa — "jim" deb hisoblanadi. */
    public const ACTIVE_DAYS = 3;

    /** @var WeakMap<Shop, array<string, mixed>>|null */
    private static ?WeakMap $cache = null;

    /** @return array<string, mixed> */
    public static function for(Shop $shop): array
    {
        self::$cache ??= new WeakMap();

        return self::$cache[$shop] ??= self::build($shop);
    }

    /** @return array<string, mixed> */
    private static function build(Shop $shop): array
    {
        $p = DB::table('productions as p')
            ->leftJoin('bread_categories as b', 'b.id', '=', 'p.bread_category_id')
            ->where('p.shop_id', $shop->id)
            ->selectRaw('COUNT(*) as entries')
            ->selectRaw('COUNT(DISTINCT p.date) as days')
            ->selectRaw('MIN(p.date) as first_date')
            ->selectRaw('MAX(p.date) as last_date')
            ->selectRaw('COALESCE(SUM(p.bread_produced), 0) as produced')
            ->selectRaw('COALESCE(SUM(p.ingredient_cost), 0) as cost')
            ->selectRaw('COALESCE(SUM(p.bread_produced * COALESCE(b.selling_price, 0)), 0) as revenue')
            ->first();

        $count = fn (string $table, bool $softDeletes = false): int => DB::table($table)
            ->where('shop_id', $shop->id)
            ->when($softDeletes, fn ($q) => $q->whereNull('deleted_at'))
            ->count();

        $revenue = (float) $p->revenue;
        $cost = (float) $p->cost;
        $profit = $revenue - $cost;
        $lastDate = $p->last_date ? Carbon::parse($p->last_date) : null;

        $counts = [
            'products' => $count('bread_categories', true),
            'recipes' => $count('recipes', true),
            'ingredients' => $count('ingredients', true),
            'users' => $count('user_shops'),
            'cash' => $count('cash_transactions'),
            'orders' => $count('customer_orders'),
            'customers' => $count('customers'),
            'outlets' => $count('outlets', true),
            'returns' => $count('bread_returns'),
            'expenses' => $count('expenses'),
        ];

        return $counts + [
            'entries' => (int) $p->entries,
            'days' => (int) $p->days,
            'first_date' => $p->first_date ? Carbon::parse($p->first_date) : null,
            'last_date' => $lastDate,
            'produced' => (int) $p->produced,
            'cost' => $cost,
            'revenue' => $revenue,
            'profit' => $profit,
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
            'per_day' => $p->days > 0 ? (int) round($p->produced / $p->days) : 0,
            'status' => self::status($counts['recipes'], $lastDate),
            'warnings' => self::warnings($shop),
        ];
    }

    /** @return array{label: string, color: string} */
    public static function status(int $recipes, Carbon|string|null $lastDate): array
    {
        $lastDate = is_string($lastDate) ? Carbon::parse($lastDate) : $lastDate?->copy();

        if ($lastDate === null) {
            return $recipes > 0
                ? ['label' => 'Sozlangan, lekin boshlamagan', 'color' => 'warning']
                : ['label' => 'Sozlanmagan', 'color' => 'gray'];
        }

        $idle = (int) $lastDate->startOfDay()->diffInDays(now()->startOfDay());

        return $idle <= self::ACTIVE_DAYS
            ? ['label' => 'Faol', 'color' => 'success']
            : ['label' => "{$idle} kundan beri jim", 'color' => 'danger'];
    }

    /**
     * Sozlamadagi odatiy xatolar — admin foydalanuvchiga yordam berishi uchun.
     *
     * @return list<string>
     */
    private static function warnings(Shop $shop): array
    {
        $recipes = Recipe::query()
            ->where('shop_id', $shop->id)
            ->with(['breadCategory', 'recipeIngredients.ingredient' => fn ($q) => $q->withTrashed()])
            ->get();

        $out = [];

        $hasFlourRecipe = $recipes->contains(fn (Recipe $r) => $r->recipeIngredients
            ->contains(fn ($ri) => (bool) $ri->ingredient?->is_flour));
        if ($recipes->isNotEmpty() && ! $hasFlourRecipe) {
            $out[] = 'Hech bir xom ashyo "un" deb belgilanmagan — sarflangan un 0 kg chiqadi.';
        }

        foreach ($recipes as $r) {
            $name = $r->breadCategory?->name ?? $r->name;
            $lines = $r->recipeIngredients;

            if ($lines->isEmpty()) {
                $out[] = "\"{$name}\" retseptida xom ashyo yo'q.";
            } elseif ($lines->count() === 1) {
                $out[] = "\"{$name}\" retseptida bitta xom ashyo (\"{$lines->first()->ingredient?->name}\") — tannarx umumiy summa qilib kiritilgan bo'lishi mumkin.";
            }

            if ($lines->contains(fn ($ri) => $ri->ingredient?->deleted_at !== null)) {
                $out[] = "\"{$name}\" retseptida o'chirilgan xom ashyo bor.";
            }

            $unitCost = self::unitCost($r);
            $price = (float) ($r->breadCategory?->selling_price ?? 0);
            if ($unitCost !== null && $price > 0 && $unitCost >= $price) {
                $out[] = "\"{$name}\": tannarx (" . self::number($unitCost) . ") sotuv narxidan (" . self::number($price) . ") yuqori yoki teng.";
            }
        }

        $withoutRecipe = DB::table('bread_categories as b')
            ->where('b.shop_id', $shop->id)
            ->whereNull('b.deleted_at')
            ->whereNotExists(fn ($q) => $q->from('recipes as r')
                ->whereColumn('r.bread_category_id', 'b.id')
                ->whereNull('r.deleted_at'))
            ->pluck('b.name');
        foreach ($withoutRecipe as $name) {
            $out[] = "\"{$name}\" mahsulotining retsepti yo'q.";
        }

        $zeroPrice = DB::table('ingredients')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->where('price_per_unit', '<=', 0)
            ->pluck('name');
        if ($zeroPrice->isNotEmpty()) {
            $out[] = 'Narxi kiritilmagan xom ashyolar: ' . $zeroPrice->implode(', ') . '.';
        }

        return $out;
    }

    /** 1 partiya (qop, blok…) uchun xom ashyo xarajati. */
    public static function batchCost(Recipe $recipe): float
    {
        return (float) $recipe->recipeIngredients->sum(
            fn ($ri) => (float) $ri->quantity * (float) ($ri->ingredient?->price_per_unit ?? 0)
        );
    }

    /** Bitta mahsulot tannarxi; partiyadan chiqish noma'lum bo'lsa `null`. */
    public static function unitCost(Recipe $recipe): ?float
    {
        $output = (int) $recipe->output_quantity;

        return $output > 0 ? self::batchCost($recipe) / $output : null;
    }

    /**
     * Foydalanuvchining barcha do'konlari bo'yicha umumiy faollik.
     *
     * @return array{shops: int, owned: int, entries: int, produced: int, last_date: ?Carbon}
     */
    public static function forUser(User $user): array
    {
        $shopIds = DB::table('user_shops')->where('user_id', $user->id)->pluck('shop_id');
        $owned = DB::table('user_shops')
            ->where('user_id', $user->id)
            ->where('user_type', ShopUserType::Owner->value)
            ->count();

        $p = DB::table('productions')
            ->whereIn('shop_id', $shopIds)
            ->selectRaw('COUNT(*) as entries, COALESCE(SUM(bread_produced), 0) as produced, MAX(date) as last_date')
            ->first();

        return [
            'shops' => $shopIds->count(),
            'owned' => $owned,
            'entries' => (int) $p->entries,
            'produced' => (int) $p->produced,
            'last_date' => $p->last_date ? Carbon::parse($p->last_date) : null,
        ];
    }

    public static function number(float|int|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, '.', ' ');
    }

    public static function money(float|int|null $value, ?Shop $shop = null): string
    {
        $code = $shop?->currency?->code;

        return self::number($value) . ($code ? " {$code}" : '');
    }
}
