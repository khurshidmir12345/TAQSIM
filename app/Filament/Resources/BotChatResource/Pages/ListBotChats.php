<?php

namespace App\Filament\Resources\BotChatResource\Pages;

use App\Filament\Resources\BotChatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBotChats extends ListRecords
{
    protected static string $resource = BotChatResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
