<?php

namespace App\Filament\Resources\Vehicules\Pages;

use App\Filament\Resources\Vehicules\VehiculeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicule extends EditRecord
{
    protected static string $resource = VehiculeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return VehiculeResource::securiser($data, $this->getRecord());
    }
}
