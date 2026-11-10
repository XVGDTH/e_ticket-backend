<?php

namespace App\Filament\Resources\FraisRegles\Pages;

use App\Filament\Resources\FraisRegles\FraisRegleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFraisRegle extends CreateRecord
{
    protected static string $resource = FraisRegleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return FraisRegleResource::securiser($data);
    }
}
