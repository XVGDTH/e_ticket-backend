<?php

namespace App\Services\Finance;

use App\Enums\BilletStatut;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class RapportFinancier
{
    public const PERIODES = [
        'aujourdhui' => "Aujourd'hui",
        '7j' => '7 jours',
        '30j' => '30 jours',
        'mois' => 'Ce mois',
        'mois_prec' => 'Mois dernier',
        'annee' => 'Cette année',
        'perso' => 'Dates libres',
    ];

    public const ENTETES_TRANSACTIONS = [
        'ID', 'Référence', 'Date de paiement', 'Client', 'Téléphone', 'Compagnie', 'Gare de départ', 'Trajet',
        'Date du voyage', 'Heure', 'Voyageurs', 'Opérateur', 'Statut',
        'Prix du billet (FCFA)', 'Frais (FCFA)', 'Total payé (FCFA)',
    ];

    public function __construct(private User $user) {}

    public static function pour(User $user): self
    {
        return new self($user);
    }

    /** Super admin et admin de compagnie uniquement. */
    public static function autorise(?User $user): bool
    {
        return $user !== null && ($user->isSuperAdmin() || $user->isAdminCompagnie());
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function bornes(string $periode, ?string $debut = null, ?string $fin = null): array
    {
        try {
            return match ($periode) {
                'aujourdhui' => [now()->startOfDay(), now()->endOfDay()],
                '7j' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
                'mois' => [now()->startOfMonth(), now()->endOfDay()],
                'mois_prec' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                'annee' => [now()->startOfYear(), now()->endOfDay()],
                'perso' => [
                    $debut ? Carbon::parse($debut)->startOfDay() : now()->startOfMonth(),
                    $fin ? Carbon::parse($fin)->endOfDay() : now()->endOfDay(),
                ],
                default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            };
        } catch (\Throwable) {
            return [now()->subDays(29)->startOfDay(), now()->endOfDay()];
        }
    }

    public static function granularite(Carbon $debut, Carbon $fin): string
    {
        $jours = (int) $debut->diffInDays($fin) + 1;

        return match (true) {
            $jours <= 45 => 'jour',
            $jours <= 200 => 'semaine',
            $jours <= 800 => 'mois',
            default => 'annee',
        };
    }

    public static function libellePeriode(string $granularite, string $libelle): string
    {
        return match ($granularite) {
            'jour' => Carbon::parse($libelle)->format('d/m/Y'),
            'semaine' => 'Sem. du '.Carbon::parse($libelle)->format('d/m/Y'),
            'mois' => Carbon::parse($libelle.'-01')->format('m/Y'),
            default => $libelle,
        };
    }

    public static function libelleStatut(mixed $statut): string
    {
        $v = $statut instanceof \BackedEnum ? $statut->value : (string) $statut;

        return match ($v) {
            'valide' => 'Valide',
            'utilise' => 'Embarqué',
            'annule' => 'Annulé',
            'rembourse' => 'Remboursé',
            'en_attente_paiement' => 'En attente de paiement',
            default => ucfirst($v),
        };
    }

    /** Paiements du périmètre de l'utilisateur, avec client, compagnie, gare et villes. */
    public function base(): Builder
    {
        $q = DB::table('billets as b')
            ->join('route_transports as r', 'r.id', '=', 'b.route_transport_id')
            ->join('gares as g', 'g.id', '=', 'r.gare_depart_id')
            ->join('compagnies as c', 'c.id', '=', 'r.compagnie_id')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->leftJoin('villes as vd', 'vd.id', '=', 'g.ville_id')
            ->leftJoin('villes as va', 'va.id', '=', 'r.ville_arrivee_id')
            ->whereNotNull('b.date_paiement');

        if ($this->user->isSuperAdmin()) {
            return $q;
        }

        if ($this->user->isAdminCompagnie()) {
            $q->where('r.compagnie_id', $this->user->compagnieId() ?? 0);

            if ($villeId = $this->user->villeId()) {
                $q->where('g.ville_id', $villeId);
            }

            return $q;
        }

        return $q->whereRaw('1 = 0');
    }

    /** @param array{debut:Carbon, fin:Carbon, compagnie_id?:?int, gare_id?:?int, operateur?:?string, statut?:?string} $f */
    public function filtrer(array $f): Builder
    {
        $q = $this->base()->whereBetween('b.date_paiement', [$f['debut'], $f['fin']]);

        if (! empty($f['compagnie_id'])) {
            $q->where('r.compagnie_id', $f['compagnie_id']);
        }

        if (! empty($f['gare_id'])) {
            $q->where('r.gare_depart_id', $f['gare_id']);
        }

        if (! empty($f['operateur'])) {
            $q->where('b.operateur_paiement', $f['operateur']);
        }

        $statut = $f['statut'] ?? 'encaisses';

        if ($statut === 'encaisses') {
            $q->whereIn('b.statut', BilletStatut::comptabilises());
        } elseif ($statut !== 'tous') {
            $q->where('b.statut', $statut);
        }

        return $q;
    }

    public function periodePrecedente(array $f): array
    {
        $jours = max(1, (int) round($f['debut']->diffInDays($f['fin'])) + 1);

        return [
            ...$f,
            'debut' => $f['debut']->copy()->subDays($jours),
            'fin' => $f['debut']->copy()->subSecond(),
        ];
    }

    public function totaux(array $f): object
    {
        return $this->filtrer($f)->selectRaw(
            'COUNT(*) as billets,
            COALESCE(SUM(b.travelers_count), 0) as voyageurs,
            COALESCE(SUM(b.montant_transport), 0) as ca,
            COALESCE(SUM(b.taxe), 0) as frais,
            COALESCE(SUM(COALESCE(b.montant_transport, 0) + COALESCE(b.taxe, 0)), 0) as total,
            COUNT(DISTINCT b.user_id) as clients'
        )->first();
    }

    /** Billets remboursés sur la période (jamais comptés dans les totaux). */
    public function remboursements(array $f): object
    {
        return $this->filtrer([...$f, 'statut' => BilletStatut::Rembourse->value])
            ->selectRaw('COUNT(*) as billets, COALESCE(SUM(b.montant_transport), 0) as montant')
            ->first();
    }

    /** Ventes regroupées : compagnie, gare, ville, operateur, jour, semaine, mois ou annee. */
    public function groupe(array $f, string $dimension): Collection
    {
        [$libelle, $groupe] = match ($dimension) {
            'compagnie' => ['c.nom', 'c.id, c.nom'],
            'gare' => ["CONCAT(g.quartier, ' · ', c.nom)", 'g.id, g.quartier, c.nom'],
            'ville' => ["COALESCE(vd.nom, 'Ville inconnue')", 'vd.id, vd.nom'],
            'operateur' => ["COALESCE(b.operateur_paiement, 'Inconnu')", 'b.operateur_paiement'],
            'semaine' => [
                'DATE(DATE_SUB(DATE(b.date_paiement), INTERVAL WEEKDAY(b.date_paiement) DAY))',
                'DATE(DATE_SUB(DATE(b.date_paiement), INTERVAL WEEKDAY(b.date_paiement) DAY))',
            ],
            'mois' => ["DATE_FORMAT(b.date_paiement, '%Y-%m')", "DATE_FORMAT(b.date_paiement, '%Y-%m')"],
            'annee' => ['YEAR(b.date_paiement)', 'YEAR(b.date_paiement)'],
            default => ['DATE(b.date_paiement)', 'DATE(b.date_paiement)'],
        };

        $chrono = in_array($dimension, ['jour', 'semaine', 'mois', 'annee'], true);

        return $this->filtrer($f)
            ->selectRaw(
                "$libelle as libelle,
                COUNT(*) as billets,
                COALESCE(SUM(b.travelers_count), 0) as voyageurs,
                COALESCE(SUM(b.montant_transport), 0) as ca,
                COALESCE(SUM(b.taxe), 0) as frais,
                COALESCE(SUM(COALESCE(b.montant_transport, 0) + COALESCE(b.taxe, 0)), 0) as total"
            )
            ->groupByRaw($groupe)
            ->orderByRaw($chrono ? 'libelle asc' : 'total desc')
            ->get();
    }

    public function synthese(array $f): array
    {
        $granularite = self::granularite($f['debut'], $f['fin']);

        return [
            'totaux' => $this->totaux($f),
            'remboursements' => $this->remboursements($f),
            'granularite' => $granularite,
            'par_compagnie' => $this->groupe($f, 'compagnie'),
            'par_gare' => $this->groupe($f, 'gare'),
            'par_ville' => $this->groupe($f, 'ville'),
            'par_operateur' => $this->groupe($f, 'operateur'),
            'par_periode' => $this->groupe($f, $granularite),
        ];
    }

    public function perimetre(array $f): string
    {
        $morceaux = [];

        if ($this->user->isSuperAdmin()) {
            $morceaux[] = ! empty($f['compagnie_id'])
                ? (Compagnie::find($f['compagnie_id'])?->nom ?? 'Compagnie')
                : 'Toutes les compagnies';
        } else {
            $morceaux[] = Compagnie::find($this->user->compagnieId())?->nom ?? 'Ma compagnie';
        }

        if (! empty($f['gare_id'])) {
            $morceaux[] = 'Gare '.(Gare::find($f['gare_id'])?->quartier ?? '');
        }

        return implode(' · ', $morceaux);
    }

    /** Une rubrique d'export : lignes + ligne TOTAL. */
    public static function section(string $titre, string $colonne, Collection $lignes, ?string $granularite = null): array
    {
        $corps = $lignes->map(fn ($l) => [
            $granularite ? self::libellePeriode($granularite, (string) $l->libelle) : (string) $l->libelle,
            (int) $l->billets,
            (int) $l->voyageurs,
            (int) $l->ca,
            (int) $l->frais,
            (int) $l->total,
        ])->values()->all();

        $corps[] = [
            'TOTAL',
            (int) $lignes->sum('billets'),
            (int) $lignes->sum('voyageurs'),
            (int) $lignes->sum('ca'),
            (int) $lignes->sum('frais'),
            (int) $lignes->sum('total'),
        ];

        return [
            'titre' => $titre,
            'entetes' => [$colonne, 'Billets', 'Voyageurs', 'Prix des billets (FCFA)', 'Frais (FCFA)', 'Total encaissé (FCFA)'],
            'lignes' => $corps,
        ];
    }

    /** Détail des transactions, ligne par ligne (flux, sans tout charger en mémoire). */
    public function lignesTransactions(array $f): LazyCollection
    {
        return $this->filtrer($f)
            ->select([
                'b.id', 'b.reference', 'b.date_paiement', 'b.date_voyage', 'b.heure', 'b.statut',
                'b.operateur_paiement', 'b.travelers_count', 'b.montant_transport', 'b.taxe',
                'u.prenom', 'u.nom', 'u.telephone',
                'c.nom as compagnie', 'g.quartier as gare', 'va.nom as ville_arrivee',
            ])
            ->orderByDesc('b.date_paiement')
            ->cursor()
            ->map(fn ($b) => [
                (string) $b->id,
                (string) $b->reference,
                Carbon::parse($b->date_paiement)->format('d/m/Y H:i'),
                trim($b->prenom.' '.$b->nom),
                (string) $b->telephone,
                (string) $b->compagnie,
                (string) $b->gare,
                $b->gare.' → '.($b->ville_arrivee ?? '?'),
                Carbon::parse($b->date_voyage)->format('d/m/Y'),
                substr((string) $b->heure, 0, 5),
                (int) $b->travelers_count,
                (string) ($b->operateur_paiement ?? '—'),
                self::libelleStatut($b->statut),
                (int) $b->montant_transport,
                (int) $b->taxe,
                (int) $b->montant_transport + (int) $b->taxe,
            ]);
    }
}
