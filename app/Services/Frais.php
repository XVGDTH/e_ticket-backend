<?php

namespace App\Services;

use App\Models\FraisRegle;
use App\Models\RouteTransport;
use Illuminate\Support\Collection;

class Frais
{
    /** Grille de base, identique pour toutes les compagnies tant qu'elle n'est pas personnalisée. */
    public const GRILLE_BASE = [
        ['trajet' => 'national', 'prix_min' => 0, 'prix_max' => 1000, 'valeur' => 100],
        ['trajet' => 'national', 'prix_min' => 1001, 'prix_max' => 2500, 'valeur' => 150],
        ['trajet' => 'national', 'prix_min' => 2501, 'prix_max' => 3000, 'valeur' => 200],
        ['trajet' => 'national', 'prix_min' => 3001, 'prix_max' => 5999, 'valeur' => 250],
        ['trajet' => 'national', 'prix_min' => 6000, 'prix_max' => null, 'valeur' => 300],
        ['trajet' => 'international', 'prix_min' => 0, 'prix_max' => null, 'valeur' => 1000],
    ];

    private static ?Collection $regles = null;

    public static function oublier(): void
    {
        self::$regles = null;
    }

    private static function regles(): Collection
    {
        return self::$regles ??= FraisRegle::query()->where('actif', true)->get();
    }

    /** Les tranches de la grille de base (règles générales), du national à l'international. */
    public static function paliers(): Collection
    {
        return FraisRegle::query()
            ->whereNull('compagnie_id')->whereNull('gare_id')->whereNull('route_transport_id')
            ->whereNotNull('trajet')->where('mode', 'fixe')->where('actif', true)
            ->orderByRaw("trajet = 'international'")
            ->orderBy('prix_min')
            ->get();
    }

    /** Clé stable d'une tranche, utilisée par l'écran de la grille. */
    public static function cle(FraisRegle $r): string
    {
        return substr((string) $r->trajet, 0, 1).'_'.$r->prix_min.'_'.($r->prix_max ?? 'x');
    }

    public static function libelleTranche(FraisRegle $r): string
    {
        $fmt = fn (int $n) => number_format($n, 0, ',', ' ');

        if ($r->trajet === 'international') {
            return 'Tous les prix';
        }

        return $r->prix_max === null
            ? $fmt($r->prix_min).' et plus'
            : $fmt($r->prix_min).' à '.$fmt($r->prix_max);
    }

    /**
     * Frais d'un achat (payés une seule fois), selon la route, le prix d'un billet et le nombre de voyageurs.
     *
     * @return array{montant: int, regle: ?FraisRegle}
     */
    public static function calculer(RouteTransport $route, int $prixUnitaire, int $voyageurs = 1): array
    {
        $type = $route->estInternationale() ? 'international' : 'national';

        $regle = self::regles()
            ->filter(fn (FraisRegle $r) => ($r->compagnie_id === null || (int) $r->compagnie_id === (int) $route->compagnie_id)
                && ($r->gare_id === null || (int) $r->gare_id === (int) $route->gare_depart_id)
                && ($r->route_transport_id === null || (int) $r->route_transport_id === (int) $route->id)
                && ($r->trajet === null || $r->trajet === $type)
                && $prixUnitaire >= $r->prix_min
                && ($r->prix_max === null || $prixUnitaire <= $r->prix_max))
            ->sort(fn (FraisRegle $a, FraisRegle $b) => $b->cle() <=> $a->cle())
            ->first();

        if (! $regle) {
            return ['montant' => (int) config('billetterie.taxe_par_achat', 300), 'regle' => null];
        }

        return ['montant' => $regle->montantPour($prixUnitaire, $voyageurs), 'regle' => $regle];
    }
}
