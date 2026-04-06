<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // ── 1. Novvoylik ──────────────────────────────────────────────────
            [
                'key'         => 'bakery',
                'icon'        => '🍞',
                'color'       => '#C8A227',
                'sort_order'  => 1,
                'name_uz'     => 'Novvoylik',
                'name_uz_cyrl'=> 'Новвойлик',
                'name_ru'     => 'Хлебопекарня',
                'name_kk'     => 'Нан пісіру',
                'name_ky'     => 'Нан бышыруу',
                'name_tr'     => 'Fırıncılık',
                'terminology' => $this->terminology('Un', 'qop', 'qop', 'yopildi', 'Mahsulot turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 2. Shashlikxona ───────────────────────────────────────────────
            [
                'key'         => 'shashlik',
                'icon'        => '🍢',
                'color'       => '#E53935',
                'sort_order'  => 2,
                'name_uz'     => 'Shashlikxona',
                'name_uz_cyrl'=> 'Шашликхона',
                'name_ru'     => 'Шашлычная',
                'name_kk'     => 'Шашлықхана',
                'name_ky'     => 'Шашлыкхана',
                'name_tr'     => 'Şiş kebap',
                'terminology' => $this->terminology("Go'sht", 'kg', 'partiya', 'tayyorlandi', 'Ovqat turi', 'Xom ashyolar', 'Retsept'),
            ],
            // ── 3. Somsaxona ─────────────────────────────────────────────────
            [
                'key'         => 'samsa',
                'icon'        => '🥟',
                'color'       => '#FB8C00',
                'sort_order'  => 3,
                'name_uz'     => 'Somsaxona',
                'name_uz_cyrl'=> 'Сомсахона',
                'name_ru'     => 'Самсахана',
                'name_kk'     => 'Самсахана',
                'name_ky'     => 'Самсакана',
                'name_tr'     => 'Samsa',
                'terminology' => $this->terminology('Un', 'qop', 'qop', 'yopildi', 'Mahsulot turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 4. Fastfood ───────────────────────────────────────────────────
            [
                'key'         => 'fastfood',
                'icon'        => '🍔',
                'color'       => '#F57F17',
                'sort_order'  => 4,
                'name_uz'     => 'Fastfood',
                'name_uz_cyrl'=> 'Фастфуд',
                'name_ru'     => 'Фастфуд',
                'name_kk'     => 'Фастфуд',
                'name_ky'     => 'Фастфуд',
                'name_tr'     => 'Fast food',
                'terminology' => $this->terminology("Mahsulot", 'kg', 'partiya', 'tayyorlandi', 'Taom turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 5. Shirinliklar ───────────────────────────────────────────────
            [
                'key'         => 'sweets',
                'icon'        => '🍰',
                'color'       => '#E91E63',
                'sort_order'  => 5,
                'name_uz'     => 'Shirinliklar',
                'name_uz_cyrl'=> 'Ширинликлар',
                'name_ru'     => 'Кондитерская',
                'name_kk'     => 'Кәмпит, тәттілер',
                'name_ky'     => 'Таттуулуктар',
                'name_tr'     => 'Şekerleme',
                'terminology' => $this->terminology("Un/Shakar", 'kg', 'quti', 'tayyorlandi', 'Mahsulot turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 6. Oshxona ────────────────────────────────────────────────────
            [
                'key'         => 'restaurant',
                'icon'        => '🍽️',
                'color'       => '#2E7D32',
                'sort_order'  => 6,
                'name_uz'     => 'Oshxona / Restoran',
                'name_uz_cyrl'=> 'Ошхона / Ресторан',
                'name_ru'     => 'Столовая / Ресторан',
                'name_kk'     => 'Асхана / Мейрамхана',
                'name_ky'     => 'Ашкана / Ресторан',
                'name_tr'     => 'Lokanta / Restoran',
                'terminology' => $this->terminology("Mahsulot", 'kg', 'porsiya', 'pishirildi', 'Taom turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 7. Go'shtli mahsulotlar ───────────────────────────────────────
            [
                'key'         => 'meat',
                'icon'        => '🥩',
                'color'       => '#B71C1C',
                'sort_order'  => 7,
                'name_uz'     => "Go'shtli mahsulotlar",
                'name_uz_cyrl'=> 'Гўштли маҳсулотлар',
                'name_ru'     => 'Мясная продукция',
                'name_kk'     => 'Ет өнімдері',
                'name_ky'     => 'Эт азыктары',
                'name_tr'     => 'Et ürünleri',
                'terminology' => $this->terminology("Go'sht", 'kg', 'partiya', 'tayyorlandi', 'Mahsulot turi', 'Xom ashyolar', 'Retsept'),
            ],
            // ── 8. Ichimliklar ────────────────────────────────────────────────
            [
                'key'         => 'beverages',
                'icon'        => '🥤',
                'color'       => '#0288D1',
                'sort_order'  => 8,
                'name_uz'     => 'Ichimliklar',
                'name_uz_cyrl'=> 'Ичимликлар',
                'name_ru'     => 'Напитки',
                'name_kk'     => 'Сусындар',
                'name_ky'     => 'Суусундуктар',
                'name_tr'     => 'İçecekler',
                'terminology' => $this->terminology("Suyuqlik", 'litr', 'shisha', 'tayyorlandi', 'Mahsulot turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 9. Sut mahsulotlari ───────────────────────────────────────────
            [
                'key'         => 'dairy',
                'icon'        => '🥛',
                'color'       => '#546E7A',
                'sort_order'  => 9,
                'name_uz'     => 'Sut mahsulotlari',
                'name_uz_cyrl'=> 'Сут маҳсулотлари',
                'name_ru'     => 'Молочные продукты',
                'name_kk'     => 'Сүт өнімдері',
                'name_ky'     => 'Сүт азыктары',
                'name_tr'     => 'Süt ürünleri',
                'terminology' => $this->terminology('Sut', 'litr', 'quti', 'tayyorlandi', 'Mahsulot turi', 'Ingredientlar', 'Retsept'),
            ],
            // ── 10. Umumiy ishlab chiqarish ────────────────────────────────────
            [
                'key'         => 'general',
                'icon'        => '🏭',
                'color'       => '#455A64',
                'sort_order'  => 10,
                'name_uz'     => 'Umumiy ishlab chiqarish',
                'name_uz_cyrl'=> 'Умумий ишлаб чиқариш',
                'name_ru'     => 'Общее производство',
                'name_kk'     => 'Жалпы өндіріс',
                'name_ky'     => 'Жалпы өндүрүш',
                'name_tr'     => 'Genel üretim',
                'terminology' => $this->terminology('Xom ashyo', 'birlik', 'partiya', 'ishlab chiqarildi', 'Mahsulot turi', 'Xom ashyolar', 'Retsept'),
            ],
            // ── 11. Boshqa ─────────────────────────────────────────────────────
            [
                'key'         => 'other',
                'icon'        => '✨',
                'color'       => '#7B1FA2',
                'sort_order'  => 11,
                'name_uz'     => 'Boshqa',
                'name_uz_cyrl'=> 'Бошқа',
                'name_ru'     => 'Другое',
                'name_kk'     => 'Басқа',
                'name_ky'     => 'Башка',
                'name_tr'     => 'Diğer',
                'terminology' => $this->terminology('Xom ashyo', 'birlik', 'partiya', 'ishlab chiqarildi', 'Mahsulot turi', 'Xom ashyolar', 'Retsept'),
            ],
        ];

        foreach ($types as $data) {
            BusinessType::updateOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }

    private function terminology(
        string $rawMaterial,
        string $rawMaterialUnit,
        string $batchUnit,
        string $productionVerb,
        string $categoryLabel,
        string $ingredientsLabel,
        string $recipeLabel,
    ): array {
        return [
            'uz' => [
                'rawMaterial'      => $rawMaterial,
                'rawMaterialUnit'  => $rawMaterialUnit,
                'batchLabel'       => ucfirst($batchUnit) . 'lar',
                'batchUnit'        => $batchUnit,
                'productLabel'     => 'Mahsulot',
                'productUnit'      => 'dona',
                'productionVerb'   => $productionVerb,
                'recipeLabel'      => $recipeLabel,
                'categoryLabel'    => $categoryLabel,
                'ingredientsLabel' => $ingredientsLabel,
            ],
            'uz_CYRL' => [
                'rawMaterial'      => $rawMaterial,
                'rawMaterialUnit'  => $rawMaterialUnit,
                'batchLabel'       => 'Партиялар',
                'batchUnit'        => $batchUnit,
                'productLabel'     => 'Маҳсулот',
                'productUnit'      => 'дона',
                'productionVerb'   => $productionVerb,
                'recipeLabel'      => 'Рецепт',
                'categoryLabel'    => 'Маҳсулот тури',
                'ingredientsLabel' => 'Ингредиентлар',
            ],
            'ru' => [
                'rawMaterial'      => $rawMaterial,
                'rawMaterialUnit'  => $rawMaterialUnit,
                'batchLabel'       => 'Партии',
                'batchUnit'        => $batchUnit,
                'productLabel'     => 'Продукция',
                'productUnit'      => 'шт.',
                'productionVerb'   => 'изготовлено',
                'recipeLabel'      => 'Рецепт',
                'categoryLabel'    => 'Вид продукта',
                'ingredientsLabel' => 'Ингредиенты',
            ],
            'kk' => [
                'rawMaterial'      => $rawMaterial,
                'rawMaterialUnit'  => $rawMaterialUnit,
                'batchLabel'       => 'Партиялар',
                'batchUnit'        => $batchUnit,
                'productLabel'     => 'Өнім',
                'productUnit'      => 'дана',
                'productionVerb'   => 'жасалды',
                'recipeLabel'      => 'Рецепт',
                'categoryLabel'    => 'Өнім түрі',
                'ingredientsLabel' => 'Ингредиенттер',
            ],
            'ky' => [
                'rawMaterial'      => $rawMaterial,
                'rawMaterialUnit'  => $rawMaterialUnit,
                'batchLabel'       => 'Партиялар',
                'batchUnit'        => $batchUnit,
                'productLabel'     => 'Продукт',
                'productUnit'      => 'даана',
                'productionVerb'   => 'жасалды',
                'recipeLabel'      => 'Рецепт',
                'categoryLabel'    => 'Продукт түрү',
                'ingredientsLabel' => 'Ингредиенттер',
            ],
            'tr' => [
                'rawMaterial'      => $rawMaterial,
                'rawMaterialUnit'  => $rawMaterialUnit,
                'batchLabel'       => 'Partiler',
                'batchUnit'        => $batchUnit,
                'productLabel'     => 'Ürün',
                'productUnit'      => 'adet',
                'productionVerb'   => 'üretildi',
                'recipeLabel'      => 'Tarif',
                'categoryLabel'    => 'Ürün türü',
                'ingredientsLabel' => 'Malzemeler',
            ],
        ];
    }
}
