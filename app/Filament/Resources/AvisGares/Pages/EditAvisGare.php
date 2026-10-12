<?php

namespace App\Filament\Resources\AvisGares\Pages;

use App\Filament\Resources\AvisGares\AvisGareResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvisGare extends EditRecord
{
    protected static string $resource = AvisGareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
