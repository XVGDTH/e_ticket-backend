<?php

namespace App\Filament\Resources\Compagnies\Pages;

use App\Filament\Resources\Compagnies\CompagnieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompagnie extends EditRecord
{
    protected static string $resource = CompagnieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
