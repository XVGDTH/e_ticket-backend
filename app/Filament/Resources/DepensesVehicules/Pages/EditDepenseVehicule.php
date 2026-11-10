<?php

namespace App\Filament\Resources\DepensesVehicules\Pages;

use App\Filament\Resources\DepensesVehicules\DepenseVehiculeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepenseVehicule extends EditRecord
{
    protected static string $resource = DepenseVehiculeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return DepenseVehiculeResource::securiser($data, $this->getRecord());
    }
}
