<?php

namespace App\Filament\Resources\VoyagesCars\Pages;

use App\Filament\Resources\VoyagesCars\VoyageCarResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVoyageCar extends EditRecord
{
    protected static string $resource = VoyageCarResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return VoyageCarResource::securiser($data, $this->getRecord());
    }

    protected function afterSave(): void
    {
        $alerte = $this->getRecord()->alerteCapacite();

        if ($alerte) {
            Notification::make()->title('Capacité à vérifier')->body($alerte)->warning()->persistent()->send();
        }
    }
}
