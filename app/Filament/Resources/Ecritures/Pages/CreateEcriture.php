<?php

namespace App\Filament\Resources\Ecritures\Pages;

use App\Filament\Resources\Ecritures\EcritureResource;
use App\Services\ComptaAnalytique;
use App\Services\NumerotationFactures;
use Filament\Resources\Pages\CreateRecord;

class CreateEcriture extends CreateRecord
{
    protected static string $resource = EcritureResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EcritureResource::securiser($data);
    }

    protected function beforeCreate(): void
    {
        EcritureResource::verifier($this->data, EcritureResource::cid($this->data['compagnie_id'] ?? null));
    }

    protected function afterCreate(): void
    {
        $e = $this->getRecord();

        ComptaAnalytique::enregistrer($e, $this->data['imputations'] ?? []);

        if ($e->est_facture) {
            NumerotationFactures::attribuer($e);
        }
    }

    protected function getRedirectUrl(): string
    {
        return EcritureResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
