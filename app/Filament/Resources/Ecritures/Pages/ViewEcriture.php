<?php

namespace App\Filament\Resources\Ecritures\Pages;

use App\Filament\Resources\Ecritures\EcritureResource;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;

class ViewEcriture extends ViewRecord
{
    protected static string $resource = EcritureResource::class;

    protected string $view = 'filament.resources.ecritures.view-ecriture';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Modifier')->icon('heroicon-o-pencil-square'),
            EcritureResource::actionContrepasser(),
        ];
    }

    public function validerLigne(int $ligneId): void
    {
        $this->executer(fn () => $this->getRecord()->validerLigne(auth()->user(), $ligneId), 'Ligne validée');
    }

    public function validerSuivante(): void
    {
        $this->executer(fn () => $this->getRecord()->validerProchaineLigne(auth()->user()), 'Ligne validée');
    }

    public function validerToutes(): void
    {
        $this->executer(fn () => $this->getRecord()->comptabiliser(auth()->user()), 'Toutes les lignes sont validées');
    }

    protected function executer(\Closure $faire, string $titre): void
    {
        try {
            $faire();
            $e = $this->getRecord()->refresh();

            Notification::make()
                ->title($titre)
                ->body($e->statut === 'comptabilise' ? 'L\'écriture est comptabilisée.' : null)
                ->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Validation impossible')->body($e->getMessage())->danger()->send();
        }

        $this->getRecord()->refresh();
    }
}
