<?php

namespace App\Filament\Resources\ShopResource\RelationManagers\Concerns;

use App\Models\Currency;
use App\Models\Shop;
use Filament\Tables;

/**
 * Do'kon sahifasidagi yorliqlar — admin foydalanuvchiga xizmat ko'rsatishi
 * uchun qo'shish, tahrirlash va o'chirish ochiq (View sahifasida Filament
 * odatda faqat o'qish rejimida ko'rsatadi).
 */
trait AdminEditable
{
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function shop(): Shop
    {
        /** @var Shop */
        return $this->getOwnerRecord();
    }

    /** Yangi yozuvga do'kon valyutasi, bo'lmasa UZS — API bilan bir xil. */
    protected function shopCurrencyId(): ?string
    {
        return $this->shop()->currency_id
            ?? Currency::query()->where('code', 'UZS')->value('id');
    }

    /** @return array<int, Tables\Actions\Action|Tables\Actions\ActionGroup> */
    protected static function rowActions(): array
    {
        return [
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    /** @return array<int, Tables\Actions\BulkActionGroup> */
    protected static function bulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }
}
