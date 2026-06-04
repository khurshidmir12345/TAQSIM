<?php

namespace App\Filament\Resources\SystemLinkResource\Pages;

use App\Filament\Resources\SystemLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystemLink extends EditRecord
{
    protected static string $resource = SystemLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
