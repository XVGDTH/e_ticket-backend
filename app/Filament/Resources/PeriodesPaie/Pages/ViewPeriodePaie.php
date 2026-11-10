<?php

namespace App\Filament\Resources\PeriodesPaie\Pages;

use App\Filament\Resources\PeriodesPaie\PeriodePaieResource;
use App\Services\Personnel\AccesPersonnel;
use App\Services\Personnel\CalculPaie;
use App\Services\Personnel\Exports;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;

class ViewPeriodePaie extends ViewRecord
{
    protected static string $resource = PeriodePaieResource::class;

    protected function executer(\Closure $faire, string $titre, ?\Closure $corps = null): void
    {
        try {
            $faire();
            $this->record->refresh();
            Notification::make()->title($titre)->body($corps ? $corps() : null)->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Action refusée')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculer')->label('Calculer les bulletins')->icon('heroicon-o-calculator')
                ->visible(fn () => AccesPersonnel::paieGestion(auth()->user()) && $this->record->statut === 'brouillon')
                ->action(function () {
                    $res = null;
                    $this->executer(
                        function () use (&$res) {
                            $res = CalculPaie::generer($this->record, auth()->user());
                        },
                        'Bulletins calculés',
                        fn () => $res['bulletins'].' bulletin(s).'.($res['sans_contrat'] ? ' Sans contrat (ignorés) : '.implode(', ', $res['sans_contrat']).'.' : '')
                    );
                }),
            Action::make('valider')->label('Valider la paie')->icon('heroicon-o-lock-closed')->color('success')
                ->visible(fn () => AccesPersonnel::paieGestion(auth()->user()) && $this->record->statut === 'brouillon')
                ->requiresConfirmation()
                ->modalDescription('Les bulletins sont recalculés une dernière fois, les remboursements d\'avances sont enregistrés et la période est verrouillée.')
                ->action(fn () => $this->executer(fn () => CalculPaie::valider($this->record, auth()->user()), 'Paie validée')),
            Action::make('payer')->label('Marquer toute la paie payée')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => AccesPersonnel::paieGestion(auth()->user()) && $this->record->statut === 'validee')
                ->requiresConfirmation()
                ->action(fn () => $this->executer(fn () => CalculPaie::payerPeriode($this->record), 'Paie marquée payée')),
            Action::make('rouvrir')->label('Rouvrir')->icon('heroicon-o-lock-open')->color('danger')
                ->visible(fn () => $this->record->statut === 'validee' && auth()->user()->isSuperAdmin())
                ->requiresConfirmation()->modalDescription('Les remboursements d\'avances retenus seront annulés. Possible tant qu\'aucun bulletin n\'est payé.')
                ->action(fn () => $this->executer(fn () => CalculPaie::rouvrir($this->record), 'Période rouverte')),
            Action::make('livre')->label('Livre de paie (CSV)')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => Exports::livrePaie($this->record, auth()->user())),
            Action::make('cnss')->label('État CNSS (CSV)')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => Exports::etatCnss($this->record, auth()->user())),
        ];
    }
}
