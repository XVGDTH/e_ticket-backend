<?php

namespace App\Filament\Widgets;

use App\Models\CongeEmploye;
use App\Models\ContratEmploye;
use App\Models\DocumentEmploye;
use App\Models\Employe;
use App\Models\Presence;
use App\Services\Personnel\AccesPersonnel;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PersonnelStats extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return AccesPersonnel::visible(auth()->user());
    }

    protected function getStats(): array
    {
        $u = auth()->user();
        $alerte = today()->addDays((int) config('personnel.alerte_jours', 30));

        $effectif = Employe::query()->visiblePour($u)->where('statut', 'actif')->count();

        $aujourdhui = Presence::query()->visiblePour($u)->whereDate('date', today());
        $presents = (clone $aujourdhui)->whereIn('statut', ['present', 'retard', 'mission'])->count();
        $absents = (clone $aujourdhui)->whereIn('statut', ['absent_injustifie', 'absent_justifie'])->count();
        $enConge = (clone $aujourdhui)->whereIn('statut', ['conge', 'permission', 'maladie', 'conge_sans_solde'])->count();

        $stats = [
            Stat::make('Effectif en poste', $effectif)->icon('heroicon-o-users'),
            Stat::make('Présents aujourd\'hui', $presents)->description($absents.' absent(s) · '.$enConge.' en congé')
                ->color($absents > 0 ? 'warning' : 'success')->icon('heroicon-o-clipboard-document-check'),
        ];

        if (AccesPersonnel::gerant($u)) {
            $attente = CongeEmploye::query()->visiblePour($u)->where('statut', 'demande')->count();
            $contrats = ContratEmploye::query()->where('actif', true)->whereNotNull('date_fin')
                ->whereDate('date_fin', '<=', $alerte)->whereHas('employe', fn ($q) => $q->visiblePour($u))->count();
            $docs = DocumentEmploye::query()->whereNotNull('expire_le')->whereDate('expire_le', '<=', $alerte)
                ->whereHas('employe', fn ($q) => $q->visiblePour($u)->where('statut', '!=', 'sorti'))->count();
            $sansContrat = Employe::query()->visiblePour($u)->where('statut', 'actif')->whereDoesntHave('contratActif')->count();

            $stats[] = Stat::make('Demandes de congé', $attente)->description('en attente de décision')
                ->color($attente > 0 ? 'warning' : 'gray')->icon('heroicon-o-sun');
            $stats[] = Stat::make('Contrats à échéance', $contrats)->description('dans '.config('personnel.alerte_jours').' jours ou moins')
                ->color($contrats > 0 ? 'danger' : 'gray')->icon('heroicon-o-document-text');
            $stats[] = Stat::make('Pièces à renouveler', $docs)->description('expirées ou bientôt expirées')
                ->color($docs > 0 ? 'danger' : 'gray')->icon('heroicon-o-identification');
            $stats[] = Stat::make('Employés sans contrat', $sansContrat)->description('à régulariser avant la paie')
                ->color($sansContrat > 0 ? 'danger' : 'gray')->icon('heroicon-o-exclamation-triangle');
        }

        return $stats;
    }
}
