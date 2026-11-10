<?php

namespace App\Filament\Resources\ComptesComptables\Pages;

use App\Filament\Resources\ComptesComptables\CompteComptableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompteComptable extends EditRecord
{
    protected static string $resource = CompteComptableResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CompteComptableResource::securiser($data, $this->getRecord());
    }
}
