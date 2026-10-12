<?php

namespace App\Services\Analytics;

use App\Enums\BilletStatut;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VentesAnalytics
{
    public const GRANULARITES = [
        'jour' => 'Par jour (30 derniers)',
        'semaine' => 'Par semaine (12 dernières)',
        'mois' => 'Par mois (12 derniers)',
        'annee' => 'Par année (5 dernières)',
    ];

    public const FENETRES = [
        '30j' => '30 derniers jours',
        '90j' => '90 derniers jours',
        '12m' => '12 derniers mois',
        'tout' => 'Depuis le début',
    ];

    private const JOURS_ISO = ['lun' => 1, 'mar' => 2, 'mer' => 3, 'jeu' => 4, 'ven' => 5, 'sam' => 6, 'dim' => 7];

    public function __construct(private User $user)
    {
    }

    public static function pour(User $user): self
    {
        return new self($user);
    }

    public static function role(?User $user): ?string
    {
        return match (true) {
            $user === null => null,
            $user->isSuperAdmin() => 'super_admin',
            $user->isAdminCompagnie() => 'admin_compagnie',
            $user->isAgentGare() => 'agent_gare',
            default => null,
        };
    }

    // ---------- Périmètre selon le rôle ----------

    /** Applique le périmètre du rôle sur une requête qui contient la table route_transports (alias r). */
    private function portee(Builder $q): Builder
    {
        return match (self::role($this->user)) {
            'super_admin' => $q,
            'admin_compagnie' => $this->restreindreVille($q->where('r.compagnie_id', $this->user->compagnieId() ?? 0)),
            'agent_gare' => $q->where('r.gare_depart_id', $this->user->gareId() ?? 0),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /** Un administrateur de ville ne voit que les routes qui partent de gares de sa ville. */
    private function restreindreVille(Builder $q): Builder
    {
        $villeId = $this->user->villeId();

        return $villeId
            ? $q->whereIn('r.gare_depart_id', DB::table('gares')->select('id')->where('ville_id', $villeId))
            : $q;
    }

    /** Billets du périmètre de l'utilisateur, avec route, gare de départ, compagnie et villes. */
    public function base(): Builder
    {
        return $this->portee(
            // DB::table('billets as b')
            //     ->join('route_transports as r', 'r.id', '=', 'b.route_transport_id')
            DB::table('billets as b')
                ->whereIn('b.statut', BilletStatut::comptabilises())
                ->join('route_transports as r', 'r.id', '=', 'b.route_transport_id')
                ->join('gares as g', 'g.id', '=', 'r.gare_depart_id')
                ->join('compagnies as c', 'c.id', '=', 'r.compagnie_id')
                ->leftJoin('villes as vd', 'vd.id', '=', 'g.ville_id')
                ->leftJoin('villes as vu', 'vu.id', '=', 'r.ville_arrivee_id')
        );
    }

    private function baseHoraires(): Builder
    {
        return $this->portee(
            DB::table('horaires as h')->join('route_transports as r', 'r.id', '=', 'h.route_transport_id')
        );
    }

    private function baseRoutes(): Builder
    {
        return $this->portee(
            DB::table('route_transports as r')
                ->join('gares as g', 'g.id', '=', 'r.gare_depart_id')
                ->join('compagnies as c', 'c.id', '=', 'r.compagnie_id')
                ->leftJoin('villes as vu', 'vu.id', '=', 'r.ville_arrivee_id')
        );
    }

    // ---------- Séries temporelles ----------

    /** @return array{0: Carbon, 1: array<string,string>} début de la fenêtre et [clé => libellé] */
    private function periodes(string $g): array
    {
        [$n, $pas, $cleFmt, $libFmt] = match ($g) {
            'jour' => [30, 'Day', 'Y-m-d', 'd/m'],
            'semaine' => [12, 'Week', 'Y-m-d', 'd/m'],
            'annee' => [5, 'Year', 'Y', 'Y'],
            default => [12, 'Month', 'Y-m', 'm/Y'],
        };

        $courant = match ($g) {
            'jour' => now()->startOfDay(),
            'semaine' => now()->startOfWeek(),
            'annee' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $debut = $courant->copy()->{'sub' . $pas . 's'}($n - 1);
        $liste = [];

        for ($i = 0; $i < $n; $i++) {
            $d = $debut->copy()->{'add' . $pas . 's'}($i);
            $liste[$d->format($cleFmt)] = $d->format($libFmt);
        }

        return [$debut, $liste];
    }

    private function groupeSql(string $g, string $col): string
    {
        return match ($g) {
            'jour' => "DATE($col)",
            'semaine' => "DATE(DATE_SUB(DATE($col), INTERVAL WEEKDAY($col) DAY))",
            'annee' => "CAST(YEAR($col) AS CHAR)",
            default => "DATE_FORMAT($col, '%Y-%m')",
        };
    }

    /** @return array{0: array<int,string>, 1: array<int,float>} libellés et valeurs, périodes vides à 0 */
    public function serie(string $g, string $expression, string $colDate = 'b.date_paiement'): array
    {
        [$debut, $periodes] = $this->periodes($g);

        $lignes = $this->base()
            ->whereBetween($colDate, [$debut, now()])
            ->selectRaw($this->groupeSql($g, $colDate) . " as p, $expression as v")
            ->groupByRaw('p')
            ->get()
            ->pluck('v', 'p');

        return [
            array_values($periodes),
            array_map(fn ($cle) => (float) ($lignes[$cle] ?? 0), array_keys($periodes)),
        ];
    }

    /** Nouveaux clients (premier achat dans le périmètre) et clients actifs par période. */
    public function clients(string $g): array
    {
        [$debut, $periodes] = $this->periodes($g);

        $premiers = $this->base()
            ->selectRaw('b.user_id, MIN(b.date_paiement) as premier')
            ->groupBy('b.user_id');

        $nouveaux = DB::query()->fromSub($premiers, 't')
            ->whereBetween('t.premier', [$debut, now()])
            ->selectRaw($this->groupeSql($g, 't.premier') . ' as p, COUNT(*) as v')
            ->groupByRaw('p')
            ->get()
            ->pluck('v', 'p');

        [, $actifs] = $this->serie($g, 'COUNT(DISTINCT b.user_id)');

        return [
            array_values($periodes),
            array_map(fn ($cle) => (int) ($nouveaux[$cle] ?? 0), array_keys($periodes)),
            array_map('intval', $actifs),
        ];
    }

    /** Variation mensuelle du CA en %, sur les 12 derniers mois complets. */
    public function croissanceMensuelle(): array
    {
        $debut = now()->startOfMonth()->subMonths(13);

        $lignes = $this->base()
            ->where('b.date_paiement', '>=', $debut)
            ->where('b.date_paiement', '<', now()->startOfMonth())
            ->selectRaw("DATE_FORMAT(b.date_paiement, '%Y-%m') as p, SUM(b.montant_transport) as v")
            ->groupByRaw('p')
            ->get()
            ->pluck('v', 'p');

        $libelles = [];
        $variations = [];
        $precedent = 0.0;

        for ($i = 0; $i <= 12; $i++) {
            $mois = $debut->copy()->addMonths($i);
            $valeur = (float) ($lignes[$mois->format('Y-m')] ?? 0);

            if ($i > 0) {
                $libelles[] = $mois->format('m/Y');
                $variations[] = $precedent > 0 ? round(($valeur - $precedent) / $precedent * 100, 1) : null;
            }

            $precedent = $valeur;
        }

        return [$libelles, $variations];
    }

    // ---------- Indicateurs ----------

    public function agregat(Carbon $de, Carbon $a): object
    {
        return $this->base()
            ->whereBetween('b.date_paiement', [$de, $a])
            ->selectRaw(
                "COUNT(*) as billets,
                COALESCE(SUM(b.montant_transport), 0) as ca,
                COALESCE(SUM(b.taxe), 0) as taxes,
                COALESCE(SUM(b.travelers_count), 0) as voyageurs,
                COUNT(DISTINCT CONCAT(b.route_transport_id, '|', b.date_voyage, '|', b.heure)) as departs"
            )
            ->first();
    }

    public static function variation(float $actuel, float $precedent): ?float
    {
        return $precedent > 0 ? round(($actuel - $precedent) / $precedent * 100, 1) : null;
    }

    // ---------- Classements ----------

    private function fenetre(Builder $q, string $f): Builder
    {
        $debut = match ($f) {
            '30j' => now()->subDays(30)->startOfDay(),
            '90j' => now()->subDays(90)->startOfDay(),
            '12m' => now()->subMonths(12)->startOfMonth(),
            default => null,
        };

        return $debut ? $q->where('b.date_paiement', '>=', $debut) : $q;
    }

    private function classement(string $f, string $label, string $groupe, string $valeur = 'SUM(b.montant_transport)', int $limite = 10): array
    {
        $lignes = $this->fenetre($this->base(), $f)
            ->selectRaw("$label as label, $valeur as v")
            ->groupByRaw($groupe)
            ->orderByDesc('v')
            ->limit($limite)
            ->get();

        return [
            $lignes->pluck('label')->all(),
            $lignes->pluck('v')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    public function caParGare(string $f): array
    {
        $label = self::role($this->user) === 'super_admin' ? "CONCAT(g.quartier, ' – ', c.nom)" : 'g.quartier';

        return $this->classement($f, $label, 'g.id, g.quartier, c.nom');
    }

    public function caParVille(string $f): array
    {
        return $this->classement($f, "COALESCE(vd.nom, 'Ville inconnue')", 'vd.id, vd.nom');
    }

    public function caParRoute(string $f): array
    {
        $label = self::role($this->user) === 'super_admin'
            ? "CONCAT(c.nom, ' · ', g.quartier, ' → ', COALESCE(vu.nom, '?'))"
            : "CONCAT(g.quartier, ' → ', COALESCE(vu.nom, '?'))";

        return $this->classement($f, $label, 'r.id, c.nom, g.quartier, vu.nom');
    }

    public function caParCompagnie(string $f): array
    {
        return $this->classement($f, 'c.nom', 'c.id, c.nom');
    }

    public function caParOperateur(string $f): array
    {
        return $this->classement($f, 'b.operateur_paiement', 'b.operateur_paiement');
    }

    /** Voyageurs par jour de la semaine ou par heure de départ (90 derniers jours de voyage). */
    public function affluence(string $dimension): array
    {
        $q = $this->base()->where('b.date_voyage', '>=', now()->subDays(90)->startOfDay());

        if ($dimension === 'heure') {
            $lignes = $q->selectRaw('CAST(LEFT(b.heure, 2) AS UNSIGNED) as k, SUM(b.travelers_count) as v')
                ->groupByRaw('k')->get()->pluck('v', 'k');
            $heures = range(0, 23);

            return [
                array_map(fn ($h) => sprintf('%02dh', $h), $heures),
                array_map(fn ($h) => (int) ($lignes[$h] ?? 0), $heures),
            ];
        }

        $lignes = $q->selectRaw('WEEKDAY(b.date_voyage) as k, SUM(b.travelers_count) as v')
            ->groupByRaw('k')->get()->pluck('v', 'k');

        return [
            ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            array_map(fn ($i) => (int) ($lignes[$i] ?? 0), range(0, 6)),
        ];
    }

    // ---------- Remplissage réel ----------

    /** @return array<int,int> numéro de jour ISO (1 = lundi) => nombre d'occurrences dans la fenêtre */
    private function occurrencesParJour(Carbon $debut, Carbon $fin): array
    {
        $occurrences = array_fill(1, 7, 0);

        for ($jour = $debut->copy()->startOfDay(); $jour->lte($fin); $jour->addDay()) {
            $occurrences[$jour->dayOfWeekIso]++;
        }

        return $occurrences;
    }

    /**
     * Nombre de départs programmés par route sur la fenêtre.
     * jours_semaine null ou vide = tous les jours ; sinon des jours abrégés comme ['lun', 'mar'].
     *
     * @return array<int,int> route_transport_id => départs
     */
    public function departsParRoute(Carbon $debut, Carbon $fin): array
    {
        $occurrences = $this->occurrencesParJour($debut, $fin);
        $total = array_sum($occurrences);
        $resultat = [];

        foreach ($this->baseHoraires()->get(['h.route_transport_id', 'h.jours_semaine']) as $ligne) {
            $liste = $ligne->jours_semaine ? json_decode($ligne->jours_semaine, true) : null;

            if (! is_array($liste) || $liste === []) {
                $n = $total;
            } else {
                $isos = [];

                foreach ($liste as $jour) {
                    $iso = self::JOURS_ISO[mb_substr(Str::lower(Str::ascii((string) $jour)), 0, 3)] ?? null;

                    if ($iso) {
                        $isos[$iso] = true;
                    }
                }

                $n = 0;

                foreach (array_keys($isos) as $iso) {
                    $n += $occurrences[$iso];
                }
            }

            $resultat[$ligne->route_transport_id] = ($resultat[$ligne->route_transport_id] ?? 0) + $n;
        }

        return $resultat;
    }

    public function departsProgrammes(Carbon $debut, Carbon $fin): int
    {
        return array_sum($this->departsParRoute($debut, $fin));
    }

    /** @return array{departs:int, voyageurs:int, capacite:int, taux:?float} */
    public function remplissage(Carbon $debut, Carbon $fin): array
    {
        $places = (int) config('analytics.places_par_depart', 60);
        $departs = $this->departsProgrammes($debut, $fin);
        $voyageurs = (int) $this->base()
            ->whereBetween('b.date_voyage', [$debut->toDateString(), $fin->toDateString()])
            ->sum('b.travelers_count');
        $capacite = $departs * $places;

        return [
            'departs' => $departs,
            'voyageurs' => $voyageurs,
            'capacite' => $capacite,
            'taux' => $capacite > 0 ? round($voyageurs / $capacite * 100, 1) : null,
        ];
    }

    /** Taux de remplissage par route (30 derniers jours terminés). $mode : 'top' ou 'bas'. */
    public function remplissageParRoute(string $mode = 'top', int $jours = 30, int $limite = 10): array
    {
        $debut = now()->subDays($jours)->startOfDay();
        $fin = now()->subDay()->endOfDay();
        $places = (int) config('analytics.places_par_depart', 60);
        $departs = $this->departsParRoute($debut, $fin);

        $voyageurs = $this->base()
            ->whereBetween('b.date_voyage', [$debut->toDateString(), $fin->toDateString()])
            ->selectRaw('r.id as route_id, SUM(b.travelers_count) as v')
            ->groupBy('r.id')
            ->get()
            ->pluck('v', 'route_id');

        $label = self::role($this->user) === 'super_admin'
            ? "CONCAT(c.nom, ' · ', g.quartier, ' → ', COALESCE(vu.nom, '?'))"
            : "CONCAT(g.quartier, ' → ', COALESCE(vu.nom, '?'))";

        $lignes = $this->baseRoutes()
            ->selectRaw("r.id, $label as label")
            ->get()
            ->map(function ($route) use ($departs, $voyageurs, $places) {
                $capacite = ($departs[$route->id] ?? 0) * $places;

                return [
                    'label' => $route->label,
                    'taux' => $capacite > 0 ? round(((float) ($voyageurs[$route->id] ?? 0)) / $capacite * 100, 1) : null,
                ];
            })
            ->filter(fn ($l) => $l['taux'] !== null);

        $lignes = ($mode === 'bas' ? $lignes->sortBy('taux') : $lignes->sortByDesc('taux'))->take($limite)->values();

        return [$lignes->pluck('label')->all(), $lignes->pluck('taux')->all()];
    }

    // ---------- Filtre pour les requêtes Eloquent ----------

    /** Filtre Eloquent sur les billets, pour les requêtes qui partent du modèle User. */
    public function filtreBillets(?int $compagnieId = null, ?int $gareId = null): \Closure
    {
        $user = $this->user;
        $role = self::role($user);

        // return function ($billets) use ($user, $role, $compagnieId, $gareId) {
        return function ($billets) use ($user, $role, $compagnieId, $gareId) {
            $billets->whereIn('statut', BilletStatut::comptabilises());
            $billets->whereHas('route', function ($r) use ($user, $role, $compagnieId, $gareId) {
                if ($role === 'admin_compagnie') {
                    $r->where('compagnie_id', $user->compagnieId() ?? 0);

                    if ($user->villeId()) {
                        $r->whereHas('gareDepart', fn ($g) => $g->where('ville_id', $user->villeId()));
                    }
                } elseif ($role === 'agent_gare') {
                    $r->where('gare_depart_id', $user->gareId() ?? 0);
                } elseif ($role !== 'super_admin') {
                    $r->whereRaw('1 = 0');
                }

                if ($compagnieId) {
                    $r->where('compagnie_id', $compagnieId);
                }

                if ($gareId) {
                    $r->where('gare_depart_id', $gareId);
                }
            });
        };
    }
}