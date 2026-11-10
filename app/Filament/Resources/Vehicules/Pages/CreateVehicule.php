<?php

namespace App\Filament\Resources\Vehicules\Pages;

use App\Filament\Resources\Vehicules\VehiculeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicule extends CreateRecord
{
    protected static string $resource = VehiculeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return VehiculeResource::securiser($data);
    }
}
