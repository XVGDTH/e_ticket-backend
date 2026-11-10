<?php

namespace App\Filament\Resources\ColisCourriers\Pages;

use App\Filament\Resources\ColisCourriers\ColisResource;
use Filament\Resources\Pages\EditRecord;

class EditColis extends EditRecord
{
    protected static string $resource = ColisResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ColisResource::securiser($data, $this->getRecord());
    }
}
