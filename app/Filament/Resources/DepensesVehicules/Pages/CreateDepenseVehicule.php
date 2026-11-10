<?php

namespace App\Filament\Resources\DepensesVehicules\Pages;

use App\Filament\Resources\DepensesVehicules\DepenseVehiculeResource;
use App\Models\Vehicule;
use Filament\Resources\Pages\CreateRecord;

class CreateDepenseVehicule extends CreateRecord
{
    protected static string $resource = DepenseVehiculeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return DepenseVehiculeResource::securiser($data);
    }

    protected function afterCreate(): void
    {
        $d = $this->getRecord();

        // Le compteur relevé ne fait jamais reculer le kilométrage du car.
        if ($d->kilometrage) {
            Vehicule::whereKey($d->vehicule_id)->where('km_actuel', '<', $d->kilometrage)->update(['km_actuel' => $d->kilometrage]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return DepenseVehiculeResource::getUrl('index');
    }
}
