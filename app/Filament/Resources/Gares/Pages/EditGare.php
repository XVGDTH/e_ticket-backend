<?php

namespace App\Filament\Resources\Gares\Pages;

use App\Filament\Resources\Gares\GareResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGare extends EditRecord
{
    protected static string $resource = GareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
