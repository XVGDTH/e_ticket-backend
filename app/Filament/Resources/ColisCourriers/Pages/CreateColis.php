<?php

namespace App\Filament\Resources\ColisCourriers\Pages;

use App\Filament\Resources\ColisCourriers\ColisResource;
use Filament\Resources\Pages\CreateRecord;

class CreateColis extends CreateRecord
{
    protected static string $resource = ColisResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ColisResource::securiser($data);
    }

    protected function getRedirectUrl(): string
    {
        return ColisResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
