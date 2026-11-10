<?php

namespace App\Filament\Resources\ColisCourriers\Pages;

use App\Filament\Resources\ColisCourriers\ColisResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewColis extends ViewRecord
{
    protected static string $resource = ColisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ColisResource::actionExpedier(),
            ColisResource::actionArriver(),
            ColisResource::actionRemettre(),
            ColisResource::actionIncident(),
            EditAction::make(),
            ColisResource::actionRecu(),
        ];
    }
}
