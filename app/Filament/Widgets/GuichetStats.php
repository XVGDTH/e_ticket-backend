<?php

namespace App\Filament\Widgets;

use App\Models\Billet;
use App\Models\Caisse;
use App\Services\Guichet\AccesGuichet;
use App\Services\Guichet\VenteGuichet;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GuichetStats extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        return AccesGuichet::visible(auth()->user());
    }

    protected function getStats(): array
    {
        $u = auth()->user();

        $jour = Billet::query()->visiblesPar($u)->where('canal', 'guichet')->whereDate('billets.created_at', today())
            ->whereIn('statut', ['valide', 'utilise'])->get(['montant_transport', 'travelers_count']);

        $stats = [
            Stat::make('Ventes du guichet aujourd\'hui', number_format((int) $jour->sum('montant_transport'), 0, ',', ' ').' FCFA')
                ->description($jour->count().' billet(s), '.(int) $jour->sum('travelers_count').' voyageur(s)')->icon('heroicon-o-banknotes')->color('success'),
        ];

        $c = VenteGuichet::caisseOuverte($u);

        $stats[] = Stat::make('Ma caisse', $c ? 'Ouverte depuis '.$c->ouverte_le->format('H:i') : 'Fermée')
            ->description($c ? number_format($c->especesAttendues(), 0, ',', ' ').' FCFA d\'espèces attendues' : 'Ouvrez-la pour vendre')
            ->icon('heroicon-o-calculator')->color($c ? 'success' : 'gray');

        if (AccesGuichet::gerant($u)) {
            $ouvertes = Caisse::query()->visiblePour($u)->where('statut', 'ouverte')->count();
            $avalider = Caisse::query()->visiblePour($u)->where('statut', 'fermee')->count();

            $stats[] = Stat::make('Caisses ouvertes', $ouvertes)->icon('heroicon-o-lock-open')->color($ouvertes ? 'warning' : 'gray');
            $stats[] = Stat::make('Caisses à valider', $avalider)->icon('heroicon-o-check-badge')->color($avalider ? 'danger' : 'gray');
        }

        return $stats;
    }
}
