<?php

namespace App\Filament\Resources\Employes\Pages;

use App\Filament\Resources\Employes\EmployeResource;
use Filament\Resources\Pages\EditRecord;

class EditEmploye extends EditRecord
{
    protected static string $resource = EmployeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EmployeResource::securiser($data, $this->getRecord());
    }
}
