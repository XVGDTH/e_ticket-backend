<?php

namespace App\Filament\Resources\VoyagesCars\Pages;

use App\Filament\Resources\VoyagesCars\VoyageCarResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateVoyageCar extends CreateRecord
{
    protected static string $resource = VoyageCarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return VoyageCarResource::securiser($data);
    }

    protected function afterCreate(): void
    {
        $alerte = $this->getRecord()->alerteCapacite();

        if ($alerte) {
            Notification::make()->title('Capacité à vérifier')->body($alerte)->warning()->persistent()->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return VoyageCarResource::getUrl('index');
    }
}
