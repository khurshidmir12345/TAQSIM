<?php

namespace App\Filament\Resources\BusinessTypeResource\Pages;

use App\Filament\Resources\BusinessTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinessTypes extends ListRecords
{
    protected static string $resource = BusinessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
