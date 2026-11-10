<?php

namespace App\Services\ControleGestion;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\BulletinPaie;
use App\Models\CgParametre;
use App\Models\ChargeStructure;
use App\Models\DepenseVehicule;
use App\Models\Employe;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Vehicule;
use App\Models\VoyageCar;
use App\Services\Flotte\Rentabilite;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Modèle de coûts : rattache chaque produit et chaque charge au DÉPART (un voyage d'un car),
 * puis remonte au car, à la ligne, à la gare, à l'activité et à la compagnie.
 *
 * Paliers (RG-CDG-01) : recettes -> marge sur coûts variables -> marge sur coûts directs
 * -> contribution après coûts de gare -> résultat après frais de siège.
 */
class ModeleCouts
{
    private const POSTES = ['carburant', 'route', 'equipage', 'maintenance', 'pneus', 'fixes_car', 'incidents'];

    private const ARGENT = ['carburant', 'route', 'commissions', 'equipage', 'maintenance', 'pneus', 'fixes_car', 'incidents', 'gare', 'siege'];

    private const CHAMPS = [
        'voyages', 'pax', 'pax_app', 'pax_guichet', 'places_off', 'km', 'pax_km', 'sieges_km',
        'recettes_app', 'recettes_guichet', 'recettes_colis',
        'carburant', 'route', 'commissions', 'equipage', 'maintenance', 'pneus', 'fixes_car', 'incidents', 'gare', 'siege',
        'carb_compare_reel', 'carb_compare_std', 'ecart_prix_carb', 'ecart_qte_carb',
    ];

    public static function analyser(int $compagnieId, Carbon $debut, Carbon $fin, ?User $acteur = null): array
    {
        $debut = $debut->copy()->startOfDay();
        $fin = ($fin->gt(today()) ? today()->copy() : $fin->copy())->startOfDay();
        $jours = max(0, Vehicule::jours($debut, $fin) + 1);
        $p = CgParametre::pour($compagnieId);

        if ($debut->gt($fin)) {
            return self::assembler($debut, $fin, $jours, $p, [], [], null, self::qualiteVide());
        }

        $d1 = $debut->toDateString();
        $d2 = $fin->toDateString();

        $vehicules = Vehicule::where('compagnie_id', $compagnieId)->get()->keyBy('id');

        $voyages = VoyageCar::query()
            ->with(['chauffeur', 'gare', 'route.gareDepart.ville', 'route.villeArrivee'])
            ->where('compagnie_id', $compagnieId)->where('statut', '!=', 'annule')
            ->whereBetween('date_voyage', [$d1, $d2])
            ->get();

        if ($voyages->isEmpty()) {
            return self::assembler($debut, $fin, $jours, $p, [], [], null, self::qualiteVide());
        }

        // Billets de l'application (hors frais de service) par départ : même convention que la rentabilité des cars.
        $billets = Billet::query()
            ->whereIn('route_transport_id', RouteTransport::where('compagnie_id', $compagnieId)->select('id'))
            ->where('canal', 'app')
            ->whereBetween('date_voyage', [$d1, $d2])
            ->whereIn('statut', BilletStatut::comptabilises())
            ->get(['route_transport_id', 'date_voyage', 'heure', 'montant_transport', 'travelers_count'])
            ->groupBy(fn ($b) => $b->route_transport_id.'|'.Carbon::parse($b->date_voyage)->toDateString().'|'.Rentabilite::heure((string) $b->heure))
            ->map(fn ($g) => ['montant' => (int) $g->sum('montant_transport'), 'pax' => (int) $g->sum('travelers_count')]);

        // ------------------------------------------------------------------ 1. une ligne par départ
        $lignes = [];
        $idsParCar = [];
        $idsParJour = [];
        $groupes = $voyages->groupBy(fn ($v) => $v->route_transport_id.'|'.$v->date_voyage->toDateString().'|'.Rentabilite::heure((string) $v->heure));

        foreach ($groupes as $cle => $groupe) {
            $dep = $billets->get($cle, ['montant' => 0, 'pax' => 0]);
            $totalPlaces = max(1, (int) $groupe->sum(fn ($v) => (int) ($vehicules->get($v->vehicule_id)?->places ?? 0)));

            foreach ($groupe as $v) {
                $car = $vehicules->get($v->vehicule_id);
                $route = $v->route;
                $places = (int) ($car?->places ?? 0);
                $part = $places / $totalPlaces;

                $kmReel = (int) $v->km_parcourus;
                $kmEstime = (int) ($route?->distance_km ?? 0);
                $km = $kmReel > 0 ? $kmReel : $kmEstime;

                $paxApp = (int) round($dep['pax'] * $part);
                $paxGuichet = (int) $v->places_guichet;
                $pax = $paxApp + $paxGuichet;
                $recApp = (int) round($dep['montant'] * $part);

                $gareId = $v->gare_id ?: $route?->gare_depart_id;
                $gare = $v->gare ?? $route?->gareDepart;

                $lignes[$v->id] = [
                    'voyage_id' => (int) $v->id,
                    'date' => $v->date_voyage->toDateString(),
                    'heure' => Rentabilite::heure((string) $v->heure),
                    'statut' => $v->statut,
                    'vehicule_id' => (int) $v->vehicule_id,
                    'car' => $car?->libelle() ?? 'Car inconnu',
                    'chauffeur' => $v->chauffeur?->nomComplet(),
                    'route_id' => (int) $v->route_transport_id,
                    'ligne' => ($route?->gareDepart?->quartier ?? '?').' → '.($route?->villeArrivee?->nom ?? '?'),
                    'gare_id' => $gareId ? (int) $gareId : null,
                    'gare_nom' => $gare?->quartier ?? '—',
                    'international' => (bool) $route?->estInternationale(),
                    'voyages' => 1,
                    'places_off' => $places,
                    'pax' => $pax,
                    'pax_app' => $paxApp,
                    'pax_guichet' => $paxGuichet,
                    'km' => $km,
                    'km_estime' => $kmReel <= 0 && $kmEstime > 0,
                    'pax_km' => $pax * $km,
                    'sieges_km' => $places * $km,
                    'recettes_app' => $recApp,
                    'recettes_guichet' => (int) $v->recette_guichet,
                    'recettes_colis' => (int) $v->recette_colis,
                    'commissions' => $recApp * $p['commission_paiement_pct'] / 100,
                    'carburant' => 0.0,
                    'litres' => 0.0,
                    'route' => 0.0,
                    'equipage' => 0.0,
                    'equipage_paie' => false,
                    'heures' => 0.0,
                    'maintenance' => 0.0,
                    'pneus' => 0.0,
                    'fixes_car' => 0.0,
                    'incidents' => 0.0,
                    'gare' => 0.0,   // coût de gare ou d'agence réparti sur ce départ
                    'siege' => 0.0,  // part des frais de siège et d'atelier
                ];

                $idsParCar[(int) $v->vehicule_id][] = (int) $v->id;
                $idsParJour[(int) $v->vehicule_id][$v->date_voyage->toDateString()][] = (int) $v->id;
            }
        }

        // ------------------------------------------------------------------ 1 bis. équipage : coût horaire issu de la paie
        $infoPaie = self::equipageParPaie($lignes, $voyages);
        $equipageIgnore = 0.0;

        // ------------------------------------------------------------------ 2. dépenses des cars
        $depenses = DepenseVehicule::query()->where('compagnie_id', $compagnieId)
            ->where(function ($q) use ($d1, $d2) {
                $q->where(fn ($q) => $q->whereNull('couvre_du')->whereBetween('date_depense', [$d1, $d2]))
                    ->orWhere(fn ($q) => $q->whereNotNull('couvre_du')->whereNotNull('couvre_au')
                        ->whereDate('couvre_du', '<=', $d2)->whereDate('couvre_au', '>=', $d1));
            })->get();

        $hors = array_fill_keys(self::POSTES, 0.0);
        $fixes = [];
        $pleins = [];

        foreach ($depenses as $d) {
            $m = self::partPeriode($d, $debut, $fin);

            if ($m <= 0) {
                continue;
            }

            $poste = self::poste($d);
            $vid = (int) $d->vehicule_id;

            if ($poste === 'fixes_car') {
                $fixes[$vid] = ($fixes[$vid] ?? 0) + $m;

                continue;
            }

            // Rattachée directement à un départ (primes, frais de route, plein fait pendant le voyage).
            if ($d->voyage_id && isset($lignes[$d->voyage_id])) {
                $lignes[$d->voyage_id][$poste] += $m;

                if ($poste === 'carburant') {
                    $lignes[$d->voyage_id]['litres'] += (float) $d->litres;
                }

                continue;
            }

            // Équipage non rattaché à un voyage : seulement sur les départs que la paie ne chiffre pas (sinon doublon).
            if ($poste === 'equipage') {
                $ids = array_values(array_filter($idsParCar[$vid] ?? [], fn ($id) => ! $lignes[$id]['equipage_paie']));

                if ($ids === [] && ! empty($idsParCar[$vid])) {
                    $equipageIgnore += $m;

                    continue;
                }

                $parts = self::repartir(self::poidsKm($ids, $lignes), $m);

                if ($parts === []) {
                    $hors[$poste] += $m;
                }

                foreach ($parts as $id => $x) {
                    $lignes[$id][$poste] += $x;
                }

                continue;
            }

            if ($poste === 'carburant') {
                $pleins[$vid][] = $d;

                continue;
            }

            // Sinon : au car, puis au kilomètre sur ses départs de la période.
            $parts = self::repartir(self::poidsKm($idsParCar[$vid] ?? [], $lignes), $m);

            if ($parts === []) {
                $hors[$poste] += $m;
            }

            foreach ($parts as $id => $x) {
                $lignes[$id][$poste] += $x;
            }
        }

        // Chaque plein est réparti entre les départs effectués depuis le plein précédent, au prorata des km.
        $precedents = DepenseVehicule::query()
            ->where('compagnie_id', $compagnieId)->where('categorie', 'carburant')->whereNull('voyage_id')
            ->whereDate('date_depense', '<', $d1)
            ->selectRaw('vehicule_id, MAX(date_depense) as d')->groupBy('vehicule_id')->pluck('d', 'vehicule_id');

        foreach ($pleins as $vid => $liste) {
            $prev = isset($precedents[$vid]) ? Carbon::parse($precedents[$vid])->startOfDay() : null;

            foreach (collect($liste)->sortBy([['date_depense', 'asc'], ['id', 'asc']]) as $d) {
                $jourPlein = $d->date_depense->copy()->startOfDay();

                if ($prev === null) {
                    $depart = $debut->copy();
                } elseif ($prev->lt($jourPlein)) {
                    $depart = $prev->copy()->addDay();
                } else {
                    $depart = $jourPlein->copy();
                }

                $ids = collect($idsParCar[$vid] ?? [])
                    ->filter(fn ($id) => $lignes[$id]['date'] >= $depart->toDateString() && $lignes[$id]['date'] <= $jourPlein->toDateString())
                    ->all();

                $poids = self::poidsKm($ids, $lignes);
                $parts = self::repartir($poids, (float) $d->montant);
                $litres = self::repartir($poids, (float) $d->litres);

                if ($parts === []) {
                    $hors['carburant'] += (float) $d->montant;
                }

                foreach ($parts as $id => $x) {
                    $lignes[$id]['carburant'] += $x;
                    $lignes[$id]['litres'] += $litres[$id] ?? 0;
                }

                $prev = $jourPlein;
            }
        }

        // ------------------------------------------------------------------ 3. coûts fixes du car, au jour
        $immobilisation = [];

        foreach ($vehicules as $vid => $veh) {
            $total = ($fixes[$vid] ?? 0) + $veh->amortissementPour($debut, $fin);

            if ($total <= 0) {
                continue;
            }

            $taux = $jours > 0 ? $total / $jours : 0;
            $alloue = 0.0;

            foreach ($idsParJour[$vid] ?? [] as $ids) {
                foreach ($ids as $id) {
                    $lignes[$id]['fixes_car'] += $taux / count($ids);
                }

                $alloue += $taux;
            }

            // Un jour sans voyage reste à la charge du car (immobilisation).
            $immobilisation[$vid] = max(0, $total - $alloue);
        }

        // ------------------------------------------------------------------ 4. carburant standard et écarts
        $horsStructure = ['gare' => 0.0, 'siege' => 0.0];
        $manquants = 0;

        foreach ($lignes as &$l) {
            $litresStd = $l['km'] * $p['norme_l100'] / 100;
            $carbStd = $litresStd * $p['prix_litre'];

            if ($l['carburant'] > 0 && $l['litres'] > 0) {
                $l['carb_compare_reel'] = $l['carburant'];
                $l['carb_compare_std'] = $carbStd;
                $l['ecart_prix_carb'] = $l['carburant'] - $p['prix_litre'] * $l['litres'];
                $l['ecart_qte_carb'] = ($l['litres'] - $litresStd) * $p['prix_litre'];
            } else {
                $l['carb_compare_reel'] = $l['carb_compare_std'] = $l['ecart_prix_carb'] = $l['ecart_qte_carb'] = 0;
            }

            $l['mode'] = $l['carburant'] > 0 ? 'reel' : 'standard';

            if ($l['carburant'] <= 0) {
                if ($l['statut'] === 'termine') {
                    $manquants++;
                }

                $l['carburant'] = $carbStd;   // premier chiffrage en coûts standards (RG-CDG-03)
            }

            $l['carburant_standard'] = (int) round($carbStd);
        }
        unset($l);

        // ------------------------------------------------------------------ 5. gares, agences, siège et atelier
        $structure = ChargeStructure::query()->where('compagnie_id', $compagnieId)
            ->whereDate('periode_debut', '<=', $d2)->whereDate('periode_fin', '>=', $d1)->get();

        foreach ($structure as $c) {
            $m = $c->partPour($debut, $fin);

            if ($m <= 0) {
                continue;
            }

            $champ = $c->niveau === 'gare' ? 'gare' : 'siege';
            $cible = collect($lignes)->when($c->gare_id, fn ($x) => $x->where('gare_id', (int) $c->gare_id));

            $poids = $cible->map(fn ($l) => match ($c->cle) {
                'voyageurs' => $l['pax'],
                'km' => $l['km'],
                'recettes' => $l['recettes_app'] + $l['recettes_guichet'] + $l['recettes_colis'],
                default => 1,
            })->all();

            $parts = self::repartir($poids, $m);

            if ($parts === []) {
                $horsStructure[$champ] += $m;
            }

            foreach ($parts as $id => $x) {
                $lignes[$id][$champ] += $x;
            }
        }

        // ------------------------------------------------------------------ 6. arrondi, paliers, périmètre de l'utilisateur
        foreach ($lignes as &$l) {
            foreach (self::ARGENT as $c) {
                $l[$c] = (int) round($l[$c]);
            }

            foreach (['carb_compare_reel', 'carb_compare_std', 'ecart_prix_carb', 'ecart_qte_carb'] as $c) {
                $l[$c] = (int) round($l[$c]);
            }

            $l = array_merge($l, self::paliers($l));
        }
        unset($l);

        $limite = false;

        if ($acteur) {
            $ids = VoyageCar::query()->visiblePour($acteur)->whereIn('id', array_keys($lignes))->pluck('id')->all();
            $lignes = array_intersect_key($lignes, array_flip($ids));
            $limite = ! ($acteur->isSuperAdmin() || ($acteur->isAdminCompagnie() && ! $acteur->villeId()));
        }

        $visibles = collect($lignes)->pluck('vehicule_id')->unique()->flip()->all();
        $immobilisation = array_intersect_key($immobilisation, $visibles);

        $qualite = [
            'voyages_a_cloturer' => collect($lignes)->filter(fn ($l) => in_array($l['statut'], ['programme', 'parti'], true) && $l['date'] < today()->toDateString())->count(),
            'carburant_manquant' => $manquants,
            'routes_sans_distance' => collect($lignes)->filter(fn ($l) => $l['km'] <= 0)->pluck('ligne')->unique()->values()->all(),
            'cars_sans_amortissement' => collect($lignes)->pluck('vehicule_id')->unique()
                ->filter(fn ($vid) => $vehicules->get($vid) && ! $vehicules->get($vid)->estAmortissable())
                ->map(fn ($vid) => $vehicules->get($vid)->libelle())->values()->all(),
            'chauffeurs_sans_paie' => array_values($infoPaie['chauffeurs_sans_paie']),
            'lignes_sans_duree' => array_values(array_unique($infoPaie['lignes_sans_duree'])),
            'voyages_sans_chauffeur' => collect($lignes)->filter(fn ($l) => $l['chauffeur'] === null)->count(),
            'equipage_ignore' => $limite ? 0 : (int) round($equipageIgnore),
        ];

        $horsDeparts = $limite ? null : [
            'postes' => array_map(fn ($x) => (int) round($x), $hors),
            'gare' => (int) round($horsStructure['gare']),
            'siege' => (int) round($horsStructure['siege']),
            'immobilisation' => (int) round(array_sum($immobilisation)),
        ];

        return self::assembler($debut, $fin, $jours, $p, $lignes, $immobilisation, $horsDeparts, $qualite);
    }

    // ====================================================================== équipage

    /**
     * Équipage (tableau 5.19) : coût complet horaire du chauffeur × heures de service du départ.
     * Coût complet = coût employeur du bulletin de paie du mois du voyage (à défaut, le plus proche connu),
     * heures du mois = heures hebdomadaires du contrat × 52 / 12 (40 h par défaut).
     * Heures du départ = durée de la ligne, multipliée si le car a fait plusieurs fois la distance de la ligne.
     *
     * @return array{chauffeurs_sans_paie: array<int,string>, lignes_sans_duree: array<int,string>}
     */
    private static function equipageParPaie(array &$lignes, Collection $voyages): array
    {
        $out = ['chauffeurs_sans_paie' => [], 'lignes_sans_duree' => []];

        $chauffeurIds = $voyages->pluck('chauffeur_id')->filter()->unique()->values();

        if ($chauffeurIds->isEmpty()) {
            return $out;
        }

        $employes = Employe::query()->with('contratActif')->whereIn('chauffeur_id', $chauffeurIds)->get()->keyBy('chauffeur_id');

        $bulletins = $employes->isEmpty() ? collect() : BulletinPaie::query()
            ->join('periodes_paie', 'periodes_paie.id', '=', 'bulletins_paie.periode_paie_id')
            ->whereIn('bulletins_paie.employe_id', $employes->pluck('id'))
            ->get(['bulletins_paie.employe_id', 'bulletins_paie.cout_employeur', 'periodes_paie.annee', 'periodes_paie.mois'])
            ->groupBy('employe_id');

        foreach ($voyages as $v) {
            if (! $v->chauffeur_id || ! isset($lignes[$v->id])) {
                continue;
            }

            $nom = $v->chauffeur?->nomComplet() ?: 'Chauffeur';
            $emp = $employes->get($v->chauffeur_id);
            $liste = $emp ? $bulletins->get($emp->id) : null;

            if (! $liste || $liste->isEmpty()) {
                $out['chauffeurs_sans_paie'][$v->chauffeur_id] = $nom;

                continue;
            }

            $cle = $v->date_voyage->year * 100 + $v->date_voyage->month;
            $tries = $liste->map(fn ($x) => ['k' => (int) $x->annee * 100 + (int) $x->mois, 'c' => (int) $x->cout_employeur]);
            $choix = $tries->firstWhere('k', $cle)
                ?? $tries->where('k', '<', $cle)->sortByDesc('k')->first()
                ?? $tries->sortBy('k')->first();

            if (! $choix || $choix['c'] <= 0) {
                $out['chauffeurs_sans_paie'][$v->chauffeur_id] = $nom;

                continue;
            }

            $duree = self::dureeHeures($v->route?->duree_max);

            if ($duree === null) {
                $out['lignes_sans_duree'][] = $lignes[$v->id]['ligne'];

                continue;
            }

            $hebdo = (int) ($emp->contratActif?->heures_hebdo ?: config('personnel.paie.heures_hebdo', 40));
            $heuresMois = max(1.0, $hebdo * 52 / 12);

            $distance = (int) ($v->route?->distance_km ?? 0);
            $kmReel = (int) $v->km_parcourus;
            $fois = ($distance > 0 && $kmReel > 0) ? max(1, (int) round($kmReel / $distance)) : 1;
            $heures = $duree * $fois;

            $lignes[$v->id]['equipage'] += $choix['c'] / $heuresMois * $heures;
            $lignes[$v->id]['equipage_paie'] = true;
            $lignes[$v->id]['heures'] = $heures;
        }

        return $out;
    }

    /** « 5h30 », « 5h » ou « 5,5 » deviennent des heures décimales ; null si illisible. */
    private static function dureeHeures(?string $texte): ?float
    {
        $texte = trim((string) $texte);

        if ($texte === '') {
            return null;
        }

        if (preg_match('/(\d+)\s*h\s*(\d{1,2})?/i', $texte, $m)) {
            return (int) $m[1] + (int) ($m[2] ?? 0) / 60;
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)$/', $texte, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    // ====================================================================== calculs communs

    /** Les 4 paliers et les indicateurs du métier (RG-CDG-01 et RG-CDG-02), à partir de sommes. */
    public static function paliers(array $s): array
    {
        $recettes = ($s['recettes_app'] ?? 0) + ($s['recettes_guichet'] ?? 0) + ($s['recettes_colis'] ?? 0);
        $variables = $s['carburant'] + $s['route'] + $s['commissions'];
        $directs = $s['equipage'] + $s['maintenance'] + $s['pneus'] + $s['fixes_car'] + $s['incidents'];
        $margeVariable = $recettes - $variables;
        $margeDirecte = $margeVariable - $directs;
        $contribution = $margeDirecte - $s['gare'];
        $resultat = $contribution - $s['siege'];
        $couts = $variables + $directs + $s['gare'] + $s['siege'];
        $siegesKm = (float) ($s['sieges_km'] ?? 0);
        $km = (float) ($s['km'] ?? 0);

        return [
            'recettes' => (int) $recettes,
            'couts_variables' => (int) $variables,
            'marge_variable' => (int) $margeVariable,
            'couts_directs' => (int) $directs,
            'marge_directe' => (int) $margeDirecte,
            'contribution' => (int) $contribution,
            'resultat' => (int) $resultat,
            'couts_total' => (int) $couts,
            'marge_pct' => $recettes > 0 ? round($resultat / $recettes * 100, 1) : null,
            // coefficient de remplissage pondéré par la distance : passagers-km / sièges-km
            'remplissage' => $siegesKm > 0 ? round($s['pax_km'] / $siegesKm * 100, 1) : null,
            'recette_siege_km' => $siegesKm > 0 ? round($recettes / $siegesKm, 2) : null,
            'cout_siege_km' => $siegesKm > 0 ? round($couts / $siegesKm, 2) : null,
            'recette_km' => $km > 0 ? (int) round($recettes / $km) : null,
            'cout_km' => $km > 0 ? (int) round($couts / $km) : null,
        ];
    }

    private static function somme(Collection $groupe, array $meta = []): array
    {
        $s = array_fill_keys(self::CHAMPS, 0);

        foreach ($groupe as $l) {
            foreach (self::CHAMPS as $c) {
                $s[$c] += $l[$c] ?? 0;
            }
        }

        return array_merge($meta, $s, self::paliers($s));
    }

    private static function agreger(Collection $lignes, callable $cle, callable $meta): array
    {
        return $lignes->groupBy($cle)
            ->map(fn ($g) => self::somme($g, $meta($g->first())))
            ->sortByDesc('resultat')->values()->all();
    }

    private static function assembler(Carbon $debut, Carbon $fin, int $jours, array $p, array $lignes, array $immobilisation, ?array $horsDeparts, array $qualite): array
    {
        $c = collect($lignes)->sortBy([['date', 'asc'], ['heure', 'asc']]);

        $parCar = self::agreger($c, fn ($l) => $l['vehicule_id'], fn ($l) => [
            'vehicule_id' => $l['vehicule_id'], 'libelle' => $l['car'],
        ]);

        foreach ($parCar as $i => $row) {
            $immo = (int) round($immobilisation[$row['vehicule_id']] ?? 0);
            $parCar[$i]['immobilisation'] = $immo;
            $parCar[$i]['resultat_avec_immobilisation'] = $row['resultat'] - $immo;
        }

        $total = self::somme($c);
        $total['ecart_carburant'] = [
            'reel' => $total['carb_compare_reel'],
            'standard' => $total['carb_compare_std'],
            'ecart' => $total['carb_compare_reel'] - $total['carb_compare_std'],
            'effet_prix' => $total['ecart_prix_carb'],
            'effet_quantite' => $total['ecart_qte_carb'],
        ];

        return [
            'debut' => $debut,
            'fin' => $fin,
            'jours' => $jours,
            'parametres' => $p,
            'lignes' => $c->values()->all(),
            'total' => $total,
            'par_car' => $parCar,
            'par_ligne' => self::agreger($c, fn ($l) => $l['route_id'], fn ($l) => [
                'route_id' => $l['route_id'], 'libelle' => $l['ligne'], 'international' => $l['international'],
            ]),
            'par_gare' => self::agreger($c, fn ($l) => (int) $l['gare_id'], fn ($l) => [
                'gare_id' => $l['gare_id'], 'libelle' => $l['gare_nom'],
            ]),
            'par_activite' => self::activites($c),
            'ecart_carburant' => $total['ecart_carburant'],
            'hors_departs' => $horsDeparts,
            'qualite' => $qualite,
        ];
    }

    /**
     * Activités (RG-CDG-07). Les charges d'un départ sont réparties entre voyageurs et messagerie
     * au prorata des recettes de chacune. Affrètement, personnel, location d'espaces et atelier :
     * pas encore suivis (il faudra une étiquette d'activité sur ces opérations).
     */
    private static function activites(Collection $lignes): array
    {
        $act = [
            'voyageurs_nationaux' => ['libelle' => 'Voyageurs nationaux', 'recettes' => 0.0, 'couts' => 0.0],
            'voyageurs_internationaux' => ['libelle' => 'Voyageurs internationaux', 'recettes' => 0.0, 'couts' => 0.0],
            'messagerie' => ['libelle' => 'Messagerie (colis et bagages)', 'recettes' => 0.0, 'couts' => 0.0],
        ];

        foreach ($lignes as $l) {
            $rv = $l['recettes_app'] + $l['recettes_guichet'];
            $rc = $l['recettes_colis'];
            $r = $rv + $rc;
            $cle = $l['international'] ? 'voyageurs_internationaux' : 'voyageurs_nationaux';

            $act[$cle]['recettes'] += $rv;
            $act[$cle]['couts'] += $r > 0 ? $l['couts_total'] * $rv / $r : $l['couts_total'];
            $act['messagerie']['recettes'] += $rc;
            $act['messagerie']['couts'] += $r > 0 ? $l['couts_total'] * $rc / $r : 0;
        }

        return collect($act)->map(function ($a, $cle) {
            $res = $a['recettes'] - $a['couts'];

            return [
                'cle' => $cle,
                'libelle' => $a['libelle'],
                'recettes' => (int) round($a['recettes']),
                'couts_total' => (int) round($a['couts']),
                'resultat' => (int) round($res),
                'marge_pct' => $a['recettes'] > 0 ? round($res / $a['recettes'] * 100, 1) : null,
            ];
        })->values()->all();
    }

    private static function qualiteVide(): array
    {
        return [
            'voyages_a_cloturer' => 0, 'carburant_manquant' => 0, 'routes_sans_distance' => [], 'cars_sans_amortissement' => [],
            'chauffeurs_sans_paie' => [], 'lignes_sans_duree' => [], 'voyages_sans_chauffeur' => 0, 'equipage_ignore' => 0,
        ];
    }

    // ====================================================================== outils

    /** Poste du modèle de coûts (tableau 5.19) où tombe une dépense du car. */
    private static function poste(DepenseVehicule $d): string
    {
        return match (true) {
            $d->categorie === 'carburant' => 'carburant',
            $d->categorie === 'peage' => 'route',
            $d->categorie === 'autre' && $d->source === 'cloture' => 'route', // frais divers saisis à la clôture du voyage
            $d->categorie === 'chauffeur' => 'equipage',
            in_array($d->categorie, ['entretien', 'reparation', 'lavage'], true) => 'maintenance',
            $d->categorie === 'pneus' => 'pneus',
            in_array($d->categorie, ['assurance', 'visite_technique', 'taxes'], true) => 'fixes_car',
            default => 'incidents', // amendes et autres
        };
    }

    /** Part d'une dépense qui tombe dans la période (étalée si elle couvre une durée). */
    private static function partPeriode(DepenseVehicule $d, Carbon $debut, Carbon $fin): float
    {
        if (! $d->couvre_du || ! $d->couvre_au) {
            return (float) $d->montant;
        }

        $total = Vehicule::jours($d->couvre_du, $d->couvre_au) + 1;

        if ($total <= 0) {
            return (float) $d->montant;
        }

        $a = $d->couvre_du->gt($debut) ? $d->couvre_du : $debut;
        $b = $d->couvre_au->lt($fin) ? $d->couvre_au : $fin;

        return $a->gt($b) ? 0.0 : $d->montant * (Vehicule::jours($a, $b) + 1) / $total;
    }

    /** @param array<int,int> $ids départs concernés */
    private static function poidsKm(array $ids, array $lignes): array
    {
        $poids = [];

        foreach ($ids as $id) {
            $poids[$id] = max(0, (int) $lignes[$id]['km']);
        }

        return $poids;
    }

    /** Répartit un montant au prorata des poids (à parts égales si tous les poids sont nuls). */
    private static function repartir(array $poids, float $montant): array
    {
        if ($poids === []) {
            return [];
        }

        $total = array_sum($poids);

        if ($total <= 0) {
            $poids = array_fill_keys(array_keys($poids), 1);
            $total = count($poids);
        }

        return array_map(fn ($p) => $montant * $p / $total, $poids);
    }
}
