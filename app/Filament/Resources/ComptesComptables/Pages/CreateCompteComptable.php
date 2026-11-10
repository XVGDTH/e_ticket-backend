<?php

namespace App\Filament\Resources\ComptesComptables\Pages;

use App\Filament\Resources\ComptesComptables\CompteComptableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompteComptable extends CreateRecord
{
    protected static string $resource = CompteComptableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CompteComptableResource::securiser($data);
    }
}
