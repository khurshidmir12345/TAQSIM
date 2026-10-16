<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use App\Models\Shop;
use App\Services\Admin\DirectMessage;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShop extends ViewRecord
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Do'kon egasiga yoziladi.
            DirectMessage::configure(Actions\Action::make('message'), fn (Shop $shop) => $shop->owner()),
            Actions\EditAction::make(),
        ];
    }
}
