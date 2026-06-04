<?php

namespace App\Filament\Resources\SystemLinkResource\Pages;

use App\Filament\Resources\SystemLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSystemLinks extends ListRecords
{
    protected static string $resource = SystemLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
