<?php

namespace App\Services\ControleGestion;

use App\Models\CgBudget;
use App\Models\CgBudgetLigne;
use App\Models\CgCloture;
use App\Models\CgCommentaire;
use App\Models\CgParametre;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Vehicule;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * Comparaison budget / réel d'un mois, décomposition des écarts de recettes (prix, volume, composition),
 * commentaires obligatoires au-delà du seuil, et arrêt des chiffres à la clôture mensuelle.
 */
class Reporting
{
    public const POSTES = ['commissions', 'carburant', 'equipage', 'maintenance', 'pneus', 'frais_route', 'fixes_car', 'incidents', 'gare', 'siege'];

    public const LIBELLES = [
        'recettes' => 'Recettes voyageurs',
        'colis' => 'Messagerie (non budgétée)',
        'commissions' => 'Commissions de paiement',
        'carburant' => 'Carburant ou énergie',
        'equipage' => 'Équipage',
        'maintenance' => 'Maintenance',
        'pneus' => 'Pneumatiques',
        'frais_route' => 'Frais de route et péages',
        'fixes_car' => 'Coûts fixes du car',
        'incidents' => 'Incidents et amendes',
        'gare' => 'Coûts de gare et d’agence',
        'siege' => 'Siège et atelier',
    ];

    private const SOMMES = ['departs', 'km', 'pax', 'recettes', 'colis', ...self::POSTES];

    /** Rapport du mois, prêt à être affiché, exporté ou arrêté (uniquement des valeurs simples). */
    public static function mois(int $cid, int $annee, int $mois): array
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfDay();
        $fin = $debut->copy()->endOfMonth()->startOfDay();

        $a = ModeleCouts::analyser($cid, $debut, $fin);

        $budget = CgBudget::query()->where('compagnie_id', $cid)->where('annee', $annee)
            ->where('statut', 'valide')->orderByDesc('version')->first();

        return [
            'annee' => $annee,
            'mois' => $mois,
            'debut' => $debut->toDateString(),
            'fin' => $fin->toDateString(),
            'partiel' => $fin->gt(today()),
            'budget' => $budget ? [
                'id' => $budget->id,
                'version' => $budget->version,
                'libelle' => $budget->libelle,
                'valide_le' => $budget->valide_le?->format('d/m/Y'),
            ] : null,
            'reel' => Arr::only($a, ['total', 'par_car', 'par_ligne', 'par_gare', 'par_activite', 'ecart_carburant', 'hors_departs', 'qualite', 'parametres']),
            'ecarts' => $budget ? self::comparer($cid, $budget, $debut, $fin, $a) : null,
            'genere_le' => now()->toDateTimeString(),
        ];
    }

    /** Ce qui empêche de clôturer le mois (vide = prêt). */
    public static function bloquants(array $r): array
    {
        $out = [];
        $nonClotures = (int) ($r['reel']['qualite']['voyages_a_cloturer'] ?? 0);
        $manquants = (int) ($r['ecarts']['manquants'] ?? 0);

        if ($r['partiel']) {
            $out[] = 'Le mois n’est pas terminé.';
        }

        if ($nonClotures > 0) {
            $out[] = $nonClotures.' voyage(s) à clôturer dans Flotte, Voyages des cars.';
        }

        if ($manquants > 0) {
            $out[] = $manquants.' écart(s) au-delà du seuil sans commentaire du responsable (page Écarts).';
        }

        return $out;
    }

    /** Arrête les chiffres du mois. */
    public static function cloturer(int $cid, int $annee, int $mois, User $u): CgCloture
    {
        if (CgCloture::where('compagnie_id', $cid)->where('annee', $annee)->where('mois', $mois)->exists()) {
            throw new \DomainException('Ce mois est déjà clôturé.');
        }

        $r = self::mois($cid, $annee, $mois);

        if ($b = self::bloquants($r)) {
            throw new \DomainException(implode(' ', $b));
        }

        return CgCloture::create([
            'compagnie_id' => $cid,
            'annee' => $annee,
            'mois' => $mois,
            'snapshot' => $r,
            'cloture_par' => $u->id,
            'cloture_le' => now(),
        ]);
    }

    /** Lignes pour les exports : libellé, départs, voyageurs, recettes, coûts, résultat, résultat budget, écart, commentaire. */
    public static function lignesExport(array $r): array
    {
        $out = [];

        if ($r['ecarts']) {
            foreach ([...$r['ecarts']['lignes'], $r['ecarts']['total']] as $l) {
                $out[] = [
                    $l['libelle'], $l['reel']['departs'], $l['reel']['pax'], $l['reel']['recettes'] + $l['reel']['colis'],
                    $l['reel']['couts'], $l['reel']['resultat'], $l['budget']['resultat'], $l['ecart']['resultat'], $l['commentaire'] ?? '',
                ];
            }

            return $out;
        }

        foreach ($r['reel']['par_ligne'] as $l) {
            $out[] = [$l['libelle'], $l['voyages'], $l['pax'], $l['recettes'], $l['couts_total'], $l['resultat'], null, null, ''];
        }

        $t = $r['reel']['total'];
        $out[] = ['Total de la compagnie', $t['voyages'], $t['pax'], $t['recettes'], $t['couts_total'], $t['resultat'], null, null, ''];

        return $out;
    }

    // ====================================================================== comparaison

    private static function comparer(int $cid, CgBudget $b, Carbon $debut, Carbon $fin, array $a): array
    {
        $seuil = (float) CgParametre::pour($cid)['ecart_seuil_pct'];

        // Mois en cours : le budget est proratisé au nombre de jours écoulés.
        $coef = $fin->lte(today())
            ? 1.0
            : max(0.0, min(1.0, (Vehicule::jours($debut, today()) + 1) / $debut->daysInMonth));

        $bl = CgBudgetLigne::query()->where('budget_id', $b->id)->where('mois', $debut->month)->get()->keyBy('route_transport_id');
        $reel = collect($a['par_ligne'])->keyBy('route_id');
        $ids = $bl->keys()->merge($reel->keys())->unique()->values();
        $routes = RouteTransport::with(['gareDepart', 'villeArrivee'])->whereIn('id', $ids)->get()->keyBy('id');

        $coms = CgCommentaire::query()->where('compagnie_id', $cid)->where('annee', $debut->year)
            ->where('mois', $debut->month)->pluck('texte', 'cle');

        $paires = [];

        foreach ($ids as $id) {
            $paires[$id] = [self::budgetLigne($bl->get($id), $coef), self::reelLigne($reel->get($id))];
        }

        $qb = array_sum(array_map(fn ($p) => $p[0]['pax'], $paires));
        $qr = array_sum(array_map(fn ($p) => $p[1]['pax'], $paires));

        $lignes = [];
        $tot = ['budget' => self::vide(), 'reel' => self::vide()];
        $effets = ['prix' => 0, 'volume' => 0, 'composition' => 0];

        foreach ($paires as $id => [$bud, $rl]) {
            // Prix budgété de la ligne ; à défaut (ligne non budgétée), son tarif actuel.
            $pb = $bud['pax'] > 0 ? $bud['recettes'] / $bud['pax'] : (float) ($routes->get($id)?->prix ?? 0);
            $ecartRecettes = $rl['recettes'] - $bud['recettes'];

            $prix = (int) round($rl['recettes'] - $rl['pax'] * $pb);                                   // effet prix
            $volume = (int) round(($qr - $qb) * ($qb > 0 ? $bud['pax'] / $qb : 0) * $pb);             // effet volume
            $compo = $ecartRecettes - $prix - $volume;                                                 // effet composition (le reste)

            $libelle = data_get($reel->get($id), 'libelle') ?? self::libelleRoute($routes->get($id));
            $lignes[] = self::ligne('ligne:'.$id, $libelle, $bud, $rl, ['prix' => $prix, 'volume' => $volume, 'composition' => $compo], $seuil, $coms['ligne:'.$id] ?? null);

            foreach (self::SOMMES as $k) {
                $tot['budget'][$k] += $bud[$k];
                $tot['reel'][$k] += $rl[$k];
            }

            $effets['prix'] += $prix;
            $effets['volume'] += $volume;
            $effets['composition'] += $compo;
        }

        // Ce qui n'est rattaché à aucun départ (jours d'immobilisation, charges sans départ) compte dans le réel du mois.
        $h = $a['hors_departs'];
        $tot['reel']['hors'] = $h ? (int) (array_sum($h['postes']) + $h['gare'] + $h['siege'] + $h['immobilisation']) : 0;

        $total = self::ligne('total', 'Total de la compagnie', self::finaliser($tot['budget']), self::finaliser($tot['reel']), $effets, $seuil, $coms['total'] ?? null);

        usort($lignes, fn ($x, $y) => $x['ecart']['resultat'] <=> $y['ecart']['resultat']);

        return [
            'coef' => round($coef, 3),
            'seuil' => $seuil,
            'lignes' => $lignes,
            'total' => $total,
            'manquants' => count(array_filter([...$lignes, $total], fn ($l) => $l['requis'] && blank($l['commentaire']))),
        ];
    }

    private static function ligne(string $cle, string $libelle, array $bud, array $reel, array $effets, float $seuil, ?string $commentaire): array
    {
        $ecart = [];

        foreach (['recettes', 'colis', 'couts', 'resultat', ...self::POSTES] as $k) {
            $ecart[$k] = $reel[$k] - $bud[$k];
        }

        // Écart jugé sur le résultat, rapporté à la taille de la ligne au budget.
        $base = max($bud['recettes'], abs($bud['resultat']), 1);

        return [
            'cle' => $cle,
            'libelle' => $libelle,
            'budget' => $bud,
            'reel' => $reel,
            'ecart' => $ecart,
            'effets' => $effets,
            'requis' => $ecart['resultat'] !== 0 && abs($ecart['resultat']) / $base * 100 > $seuil,
            'commentaire' => $commentaire,
        ];
    }

    private static function budgetLigne(?CgBudgetLigne $l, float $coef): array
    {
        $r = self::vide();

        if ($l) {
            foreach (['departs', 'km', 'pax', 'recettes', ...array_diff(self::POSTES, ['incidents'])] as $k) {
                $r[$k] = (int) round(((int) $l->{$k}) * $coef);
            }
        }

        return self::finaliser($r);
    }

    private static function reelLigne(?array $x): array
    {
        $r = self::vide();

        if ($x) {
            $r['departs'] = (int) $x['voyages'];
            $r['km'] = (int) $x['km'];
            $r['pax'] = (int) $x['pax'];
            $r['recettes'] = (int) ($x['recettes_app'] + $x['recettes_guichet']);
            $r['colis'] = (int) $x['recettes_colis'];

            foreach (array_diff(self::POSTES, ['frais_route']) as $k) {
                $r[$k] = (int) $x[$k];
            }

            $r['frais_route'] = (int) $x['route'];
        }

        return self::finaliser($r);
    }

    private static function vide(): array
    {
        return array_fill_keys(['departs', 'km', 'pax', 'recettes', 'colis', 'hors', 'couts', 'resultat', ...self::POSTES], 0);
    }

    private static function finaliser(array $r): array
    {
        $r['couts'] = (int) (array_sum(Arr::only($r, self::POSTES)) + $r['hors']);
        $r['resultat'] = (int) ($r['recettes'] + $r['colis'] - $r['couts']);

        return $r;
    }

    private static function libelleRoute(?RouteTransport $r): string
    {
        return ($r?->gareDepart?->quartier ?? '?').' → '.($r?->villeArrivee?->nom ?? '?');
    }
}
