<?php

namespace App\Filament\Resources\Employes\Pages;

use App\Filament\Resources\Employes\EmployeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmploye extends CreateRecord
{
    protected static string $resource = EmployeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EmployeResource::securiser($data);
    }

    protected function getRedirectUrl(): string
    {
        // Après la création, on ouvre la fiche pour ajouter le contrat et les documents.
        return EmployeResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
