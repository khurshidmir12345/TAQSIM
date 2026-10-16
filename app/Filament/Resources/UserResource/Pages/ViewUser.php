<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\DirectMessage;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DirectMessage::configure(Actions\Action::make('message'), fn (User $user) => $user),
            Actions\EditAction::make(),
        ];
    }
}
