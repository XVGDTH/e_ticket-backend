<?php

namespace App\Filament\Resources\Billets\Pages;

use App\Filament\Resources\Billets\BilletResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBillet extends EditRecord
{
    protected static string $resource = BilletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
