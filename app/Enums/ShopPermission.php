<?php

namespace App\Enums;

/**
 * Xodim (seller) uchun modul darajasidagi ruxsatlar.
 * Owner doim barcha ruxsatlarga ega.
 */
enum ShopPermission: string
{
    case ViewReports = 'view_reports';
    case ManageProducts = 'manage_products';
    case ManageRecipes = 'manage_recipes';
    case ManageProduction = 'manage_production';
    case ManageExpenses = 'manage_expenses';
    case ManageSales = 'manage_sales';
    case ManageOrders = 'manage_orders';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** Yangi xodim uchun standart ruxsatlar (owner keyin o'zgartiradi). */
    public static function defaults(): array
    {
        return [
            self::ManageProduction->value,
            self::ManageSales->value,
        ];
    }
}
