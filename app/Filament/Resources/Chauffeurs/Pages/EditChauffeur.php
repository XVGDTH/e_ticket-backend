<?php

namespace App\Filament\Resources\Chauffeurs\Pages;

use App\Filament\Resources\Chauffeurs\ChauffeurResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChauffeur extends EditRecord
{
    protected static string $resource = ChauffeurResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ChauffeurResource::securiser($data, $this->getRecord());
    }
}
