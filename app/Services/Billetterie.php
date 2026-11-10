<?php

namespace App\Services;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Billetterie
{
    private const JOURS_ISO = ['lun' => 1, 'mar' => 2, 'mer' => 3, 'jeu' => 4, 'ven' => 5, 'sam' => 6, 'dim' => 7];

    public static function placesParDepart(): int
    {
        return (int) config('analytics.places_par_depart', 60);
    }

    /** Nombre réel de places des cars de la compagnie (par départ). */
    public static function placesBusDe(?Compagnie $compagnie): int
    {
        return (int) ($compagnie?->places_par_depart ?: config('analytics.places_par_depart', 60));
    }

    /** Quota (en %) vendu par l'application : celui de la gare s'il existe, sinon celui de la compagnie. */
    public static function quotaAppDe(?Gare $gare, ?Compagnie $compagnie): int
    {
        return max(0, min(100, (int) ($gare?->quota_app_pourcent ?? $compagnie?->quota_app_pourcent ?? 100)));
    }

    /** Places que l'application peut vendre sur un départ. */
    public static function placesAppDe(?Gare $gare, ?Compagnie $compagnie): int
    {
        return (int) floor(self::placesBusDe($compagnie) * self::quotaAppDe($gare, $compagnie) / 100);
    }

    /** Même chose, à partir d'une route (sa gare de départ et sa compagnie). */
    public static function placesApp(RouteTransport $route): int
    {
        $route->loadMissing(['compagnie', 'gareDepart']);

        return self::placesAppDe($route->gareDepart, $route->compagnie);
    }

    /** "08h30", "8:30" et "08:30:00" deviennent tous "08:30". */
    public static function normaliserHeure(string $heure): string
    {
        if (preg_match('/^(\d{1,2})\s*[h:]\s*(\d{2})/i', trim($heure), $m)) {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }

        return trim($heure);
    }

    public static function jourAutorise(?array $jours, int $iso): bool
    {
        if (! $jours) {
            return true;
        }

        foreach ($jours as $jour) {
            if ((self::JOURS_ISO[mb_substr(Str::lower(Str::ascii((string) $jour)), 0, 3)] ?? null) === $iso) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heures ("HH:MM") où la route part à cette date.
     * La route doit avoir ses horaires chargés (with('horaires')).
     *
     * @return array<int,string>
     */
    public static function horairesDuJour(RouteTransport $route, string $date): array
    {
        $iso = Carbon::parse($date)->dayOfWeekIso;

        return $route->horaires
            ->filter(fn ($h) => self::jourAutorise($h->jours_semaine, $iso))
            ->map(fn ($h) => self::normaliserHeure((string) $h->heure))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Voyageurs déjà vendus pour une route et une date (billets valides ou utilisés).
     *
     * @return Collection<string,int> "HH:MM" => voyageurs
     */
    public static function vendus(int $routeId, string $date, string $canal = 'app'): Collection
    {
        return Billet::query()
            ->where('route_transport_id', $routeId)
            ->where('canal', $canal)
            ->whereDate('date_voyage', $date)
            ->whereIn('statut', BilletStatut::comptabilises())
            ->get(['heure', 'travelers_count'])
            ->groupBy(fn ($b) => self::normaliserHeure((string) $b->heure))
            ->map(fn ($groupe) => (int) $groupe->sum('travelers_count'));
    }

    /** Places que le guichet peut vendre sur un départ : les places des cars que l'application ne vend pas. */
    public static function placesGuichet(RouteTransport $route): int
    {
        $route->loadMissing(['compagnie', 'gareDepart']);

        return max(0, self::placesBusDe($route->compagnie) - self::placesApp($route));
    }
}
