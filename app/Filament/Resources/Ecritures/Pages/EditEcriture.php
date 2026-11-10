<?php

namespace App\Filament\Resources\Ecritures\Pages;

use App\Filament\Resources\Ecritures\EcritureResource;
use App\Services\ComptaAnalytique;
use App\Services\NumerotationFactures;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEcriture extends EditRecord
{
    protected static string $resource = EcritureResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Ouvrir'), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['imputations'] = ComptaAnalytique::pourFormulaire($this->getRecord());

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EcritureResource::securiser($data, $this->getRecord());
    }

    protected function beforeSave(): void
    {
        EcritureResource::verifier($this->data, EcritureResource::cid($this->data['compagnie_id'] ?? $this->getRecord()->compagnie_id));
    }

    protected function afterSave(): void
    {
        $e = $this->getRecord()->refresh();

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
