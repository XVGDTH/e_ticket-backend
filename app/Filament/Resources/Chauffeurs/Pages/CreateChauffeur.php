<?php

namespace App\Filament\Resources\Chauffeurs\Pages;

use App\Filament\Resources\Chauffeurs\ChauffeurResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChauffeur extends CreateRecord
{
    protected static string $resource = ChauffeurResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ChauffeurResource::securiser($data);
    }
}
