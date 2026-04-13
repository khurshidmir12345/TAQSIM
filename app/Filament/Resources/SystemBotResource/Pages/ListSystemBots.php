<?php

namespace App\Filament\Resources\SystemBotResource\Pages;

use App\Filament\Resources\SystemBotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSystemBots extends ListRecords
{
    protected static string $resource = SystemBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
