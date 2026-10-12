<?php

namespace App\Filament\Widgets;

use App\Models\Compagnie;
use App\Services\Analytics\VentesAnalytics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    // Pas de rafraîchissement automatique : les requêtes ne partent qu'au chargement de la page.
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return VentesAnalytics::role(auth()->user()) !== null;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $analytics = VentesAnalytics::pour($user);

        $maintenant = now();
        $actuel = $analytics->agregat($maintenant->copy()->subDays(30), $maintenant);
        $precedent = $analytics->agregat($maintenant->copy()->subDays(60), $maintenant->copy()->subDays(30));
        [, $serieCa] = $analytics->serie('jour', 'SUM(b.montant_transport)');

        $panier = fn ($a) => $a->billets > 0 ? $a->ca / $a->billets : 0;

        $places = (int) config('analytics.places_par_depart', 60);
        $remplissage = $analytics->remplissage(now()->subDays(30)->startOfDay(), now()->subDay()->endOfDay());
        $taux = $remplissage['taux'];

        $stats = [
            $this->stat("Chiffre d'affaires (30 j)", (float) $actuel->ca, (float) $precedent->ca, ' FCFA')->chart($serieCa),
            $this->stat('Billets vendus (30 j)', (float) $actuel->billets, (float) $precedent->billets),
            $this->stat('Voyageurs (30 j)', (float) $actuel->voyageurs, (float) $precedent->voyageurs),
            $this->stat('Panier moyen', (float) $panier($actuel), (float) $panier($precedent), ' FCFA'),
            $this->stat('Taxes collectées (30 j)', (float) $actuel->taxes, (float) $precedent->taxes, ' FCFA'),
            Stat::make('Remplissage réel (30 j)', $taux === null ? '—' : number_format($taux, 1, ',', ' ') . ' %')
                ->description(
                    number_format($remplissage['voyageurs'], 0, ',', ' ') . " voyageurs sur {$remplissage['departs']} départs ({$places} places chacun)"
                )
                ->color(match (true) {
                    $taux === null, $remplissage['voyageurs'] === 0 => 'gray',
                    $taux >= 70 => 'primary',
                    $taux >= 40 => 'warning',
                    default => 'danger',
                }),
        ];

        if (VentesAnalytics::role($user) === 'super_admin') {
            array_unshift($stats, Stat::make('Compagnies actives', Compagnie::where('actif', true)->count()));
        }

        return $stats;
    }

    private function stat(string $label, float $actuel, float $precedent, string $suffixe = ''): Stat
    {
        $stat = Stat::make($label, number_format($actuel, 0, ',', ' ') . $suffixe);
        $variation = VentesAnalytics::variation($actuel, $precedent);

        if ($variation === null) {
            return $stat->description('Pas de période précédente');
        }

        // return $stat
        //     ->description(($variation >= 0 ? '+' : '') . number_format($variation, 1, ',', ' ') . ' % vs 30 j précédents')
        //     ->descriptionIcon($variation >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
        //     // ->color($variation >= 0 ? 'success' : 'danger');
        //     ->color($variation >= 0 ? 'primary' : 'danger');
        [$couleur, $icone] = match (true) {
    abs($variation) < 1 => ['warning', 'heroicon-m-arrow-right'],
    $variation > 0      => ['success', 'heroicon-m-arrow-trending-up'],
    default             => ['danger', 'heroicon-m-arrow-trending-down'],
};

return $stat
    ->description(($variation >= 0 ? '+' : '') . number_format($variation, 1, ',', ' ') . ' % vs 30 j précédents')
    ->descriptionIcon($icone)
    ->color($couleur);
    }
}