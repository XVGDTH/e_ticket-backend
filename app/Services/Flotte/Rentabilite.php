<?php

namespace App\Services\Flotte;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\DepenseVehicule;
use App\Models\RouteTransport;
use App\Models\Vehicule;
use App\Models\VoyageCar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Rentabilite
{
    /** « 6h30 », « 06:30:00 » ou « 6:30 » deviennent « 06:30 ». */
    public static function heure(string $h): string
    {
        if (preg_match('/^(\d{1,2})\D+(\d{2})/', trim($h), $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return trim($h);
    }

    /** Billets de l'application (hors frais de service) par départ : « route|date|HH:MM » => montant et voyageurs. */
    private static function billets(int $compagnieId, Carbon $debut, Carbon $fin): Collection
    {
        return Billet::query()
            ->whereIn('route_transport_id', RouteTransport::where('compagnie_id', $compagnieId)->select('id'))
            ->where('canal', 'app')
            ->whereBetween('date_voyage', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('statut', BilletStatut::comptabilises())
            ->get(['route_transport_id', 'date_voyage', 'heure', 'montant_transport', 'travelers_count'])
            ->groupBy(fn ($b) => $b->route_transport_id.'|'.Carbon::parse($b->date_voyage)->toDateString().'|'.self::heure((string) $b->heure))
            ->map(fn ($g) => ['montant' => (int) $g->sum('montant_transport'), 'pax' => (int) $g->sum('travelers_count')]);
    }

    /** Part d'une dépense qui tombe dans la période (étalée si elle couvre une durée, comme une assurance annuelle). */
    private static function montantPeriode(DepenseVehicule $d, Carbon $debut, Carbon $fin): float
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

    public static function verdictLibelle(string $verdict): string
    {
        return ['rentable' => 'Rentable', 'fragile' => 'Fragile', 'deficitaire' => 'Déficitaire', 'insuffisant' => 'Données insuffisantes'][$verdict] ?? $verdict;
    }

    private static function verdict(array $c): string
    {
        if ($c['voyages'] < (int) config('flotte.min_voyages_verdict', 5) || $c['recettes'] <= 0) {
            return 'insuffisant';
        }

        if ($c['resultat'] < 0) {
            return 'deficitaire';
        }

        return $c['marge'] >= (float) config('flotte.marge_rentable', 10) ? 'rentable' : 'fragile';
    }

    private static function totauxFlotte(array $cars): array
    {
        $c = collect($cars);
        $recettes = (int) $c->sum('recettes');
        $charges = (int) $c->sum('charges');
        $amort = (int) $c->sum('amortissement');
        $placesOff = (int) $c->sum('places_offertes');
        $pax = (int) $c->sum('pax');
        $resultat = $recettes - $charges - $amort;
        $avecConso = $c->filter(fn ($x) => $x['km'] >= (int) config('flotte.km_min_consommation', 300) && $x['litres'] > 0);

        return [
            'nb_cars' => $c->count(),
            'voyages' => (int) $c->sum('voyages'),
            'voyages_moyen' => $c->count() ? round($c->sum('voyages') / $c->count(), 1) : 0,
            'pax' => $pax,
            'km' => (int) $c->sum('km'),
            'recettes' => $recettes,
            'charges' => $charges,
            'amortissement' => $amort,
            'resultat' => $resultat,
            'marge' => $recettes > 0 ? round($resultat / $recettes * 100, 1) : null,
            'remplissage' => $placesOff > 0 ? round($pax / $placesOff * 100, 1) : null,
            'l100' => $avecConso->sum('km') > 0 ? round($avecConso->sum('litres') / $avecConso->sum('km') * 100, 1) : null,
            'rentables' => $c->where('verdict', 'rentable')->count(),
            'fragiles' => $c->where('verdict', 'fragile')->count(),
            'deficitaires' => $c->where('verdict', 'deficitaire')->count(),
        ];
    }

    /**
     * Rentabilité des cars d'une compagnie sur une période.
     *
     * @param  array<int,int>|null  $vehiculeIds  limiter à ces cars (le calcul des départs partagés reste complet)
     */
    public static function analyser(int $compagnieId, Carbon $debut, Carbon $fin, ?array $vehiculeIds = null): array
    {
        $aujourdhui = today();
        $debut = $debut->copy()->startOfDay();
        $fin = ($fin->gt($aujourdhui) ? $aujourdhui->copy() : $fin->copy())->startOfDay();

        $resultat = [
            'debut' => $debut,
            'fin' => $fin,
            'jours' => max(0, Vehicule::jours($debut, $fin) + 1),
            'cars' => [],
            'flotte' => self::totauxFlotte([]),
            'non_rattache' => ['montant' => 0, 'departs' => 0],
        ];

        if ($debut->gt($fin)) {
            return $resultat;
        }

        $vehicules = Vehicule::where('compagnie_id', $compagnieId)->get()->keyBy('id');

        $voyages = VoyageCar::query()->with('chauffeur')
            ->where('compagnie_id', $compagnieId)->where('statut', '!=', 'annule')
            ->whereBetween('date_voyage', [$debut->toDateString(), $fin->toDateString()])
            ->get();

        $billets = self::billets($compagnieId, $debut, $fin);

        $routes = RouteTransport::with(['gareDepart', 'villeArrivee'])
            ->whereIn('id', $voyages->pluck('route_transport_id')->unique())->get()->keyBy('id');

        $libelleRoute = function (int $id) use ($routes): string {
            $r = $routes[$id] ?? null;

            return ($r?->gareDepart?->quartier ?? '?').' → '.($r?->villeArrivee?->nom ?? '?');
        };

        // Une ligne par voyage, avec les billets de l'application répartis entre les cars du même départ.
        $lignes = [];

        foreach ($voyages->groupBy(fn ($v) => $v->cle()) as $cle => $groupe) {
            $dep = $billets->get($cle, ['montant' => 0, 'pax' => 0]);
            $totalPlaces = max(1, (int) $groupe->sum(fn ($v) => (int) ($vehicules[$v->vehicule_id]->places ?? 0)));

            foreach ($groupe as $v) {
                $places = (int) ($vehicules[$v->vehicule_id]->places ?? 0);
                $part = $places / $totalPlaces;
                $app = (int) round($dep['montant'] * $part);

                $lignes[] = [
                    'voyage' => $v,
                    'vehicule_id' => (int) $v->vehicule_id,
                    'route_id' => (int) $v->route_transport_id,
                    'heure' => $v->heure,
                    'places' => $places,
                    'app_montant' => $app,
                    'app_pax' => (int) round($dep['pax'] * $part),
                    'guichet_pax' => (int) $v->places_guichet,
                    'recettes' => $app + (int) $v->recette_guichet + (int) $v->recette_colis,
                ];
            }
        }

        $depenses = DepenseVehicule::query()->where('compagnie_id', $compagnieId)
            ->where(function ($q) use ($debut, $fin) {
                $q->where(fn ($q) => $q->whereNull('couvre_du')
                    ->whereBetween('date_depense', [$debut->toDateString(), $fin->toDateString()]))
                    ->orWhere(fn ($q) => $q->whereNotNull('couvre_du')->whereNotNull('couvre_au')
                        ->whereDate('couvre_du', '<=', $fin->toDateString())
                        ->whereDate('couvre_au', '>=', $debut->toDateString()));
            })->get();

        $minKm = (int) config('flotte.km_min_consommation', 300);
        $cars = [];

        foreach ($vehicules as $v) {
            if ($vehiculeIds !== null && ! in_array($v->id, $vehiculeIds, true)) {
                continue;
            }

            $mes = collect($lignes)->where('vehicule_id', $v->id);
            $dep = $depenses->where('vehicule_id', $v->id);

            if ($v->statut === 'reforme' && $mes->isEmpty() && $dep->isEmpty()) {
                continue;
            }

            $parCategorie = [];

            foreach ($dep as $d) {
                $parCategorie[$d->categorie] = ($parCategorie[$d->categorie] ?? 0) + self::montantPeriode($d, $debut, $fin);
            }

            $parCategorie = array_map(fn ($m) => (int) round($m), $parCategorie);
            $parGroupe = [];

            foreach ($parCategorie as $cat => $m) {
                $g = DepenseVehicule::groupe($cat);
                $parGroupe[$g] = ($parGroupe[$g] ?? 0) + $m;
            }

            $voyagesN = $mes->count();
            $pax = (int) $mes->sum(fn ($l) => $l['app_pax'] + $l['guichet_pax']);
            $placesOff = (int) $mes->sum('places');
            $km = (int) $mes->sum(fn ($l) => (int) $l['voyage']->km_parcourus);
            $app = (int) $mes->sum('app_montant');
            $guichet = (int) $mes->sum(fn ($l) => (int) $l['voyage']->recette_guichet);
            $colis = (int) $mes->sum(fn ($l) => (int) $l['voyage']->recette_colis);
            $recettes = $app + $guichet + $colis;
            $charges = (int) array_sum($parCategorie);
            $amort = $v->amortissementPour($debut, $fin);
            $avantAmort = $recettes - $charges;
            $res = $avantAmort - $amort;
            $litres = (float) $dep->where('categorie', 'carburant')->sum('litres');
            $coutTotal = $charges + $amort;
            $recettesPax = $app + $guichet;
            $recettePax = $pax > 0 ? $recettesPax / $pax : null;
            $coutVoyage = $voyagesN > 0 ? ($coutTotal - $colis) / $voyagesN : null;
            $seuilPax = ($recettePax && $coutVoyage !== null && $coutVoyage > 0) ? (int) ceil($coutVoyage / $recettePax) : null;
            $moyPlaces = $voyagesN > 0 ? $placesOff / $voyagesN : $v->places;

            $parLigne = $mes->groupBy(fn ($l) => $l['route_id'].'|'.$l['heure'])->map(function ($g) use ($libelleRoute) {
                $places = (int) $g->sum('places');
                $pax = (int) $g->sum(fn ($l) => $l['app_pax'] + $l['guichet_pax']);

                return [
                    'route' => $libelleRoute((int) $g->first()['route_id']),
                    'heure' => str_replace(':', 'h', $g->first()['heure']),
                    'voyages' => $g->count(),
                    'pax' => $pax,
                    'remplissage' => $places > 0 ? round($pax / $places * 100, 1) : null,
                    'recettes' => (int) $g->sum('recettes'),
                    'recette_moyenne' => (int) round($g->sum('recettes') / $g->count()),
                ];
            })->sortBy('remplissage')->values()->all();

            $parChauffeur = $mes->groupBy(fn ($l) => (int) ($l['voyage']->chauffeur_id ?? 0))->map(function ($g) use ($dep, $minKm) {
                $ch = $g->first()['voyage']->chauffeur;
                $ids = $g->map(fn ($l) => $l['voyage']->id)->all();
                $kmC = (int) $g->sum(fn ($l) => (int) $l['voyage']->km_parcourus);
                $litresC = (float) $dep->where('categorie', 'carburant')->whereIn('voyage_id', $ids)->sum('litres');

                return [
                    'nom' => $ch ? $ch->nomComplet() : 'Non renseigné',
                    'voyages' => $g->count(),
                    'km' => $kmC,
                    'pax' => (int) $g->sum(fn ($l) => $l['app_pax'] + $l['guichet_pax']),
                    'l100' => ($kmC >= $minKm && $litresC > 0) ? round($litresC / $kmC * 100, 1) : null,
                ];
            })->values()->all();

            $car = [
                'id' => (int) $v->id,
                'immatriculation' => $v->immatriculation,
                'libelle' => $v->libelle(),
                'places' => (int) $v->places,
                'statut' => $v->statut,
                'voyages' => $voyagesN,
                'a_cloturer' => (int) $mes->filter(fn ($l) => in_array($l['voyage']->statut, ['programme', 'parti'], true) && $l['voyage']->date_voyage->lt(today()))->count(),
                'pax' => $pax,
                'places_offertes' => $placesOff,
                'remplissage' => $placesOff > 0 ? round($pax / $placesOff * 100, 1) : null,
                'pax_par_voyage' => $voyagesN > 0 ? round($pax / $voyagesN, 1) : null,
                'km' => $km,
                'recettes' => $recettes,
                'recettes_app' => $app,
                'recettes_guichet' => $guichet,
                'recettes_colis' => $colis,
                'charges' => $charges,
                'par_categorie' => $parCategorie,
                'par_groupe' => $parGroupe,
                'amortissement' => $amort,
                'resultat_avant_amort' => $avantAmort,
                'resultat' => $res,
                'marge' => $recettes > 0 ? round($res / $recettes * 100, 1) : null,
                'recette_km' => $km > 0 ? (int) round($recettes / $km) : null,
                'cout_km' => $km > 0 ? (int) round($coutTotal / $km) : null,
                'litres' => $litres,
                'l100' => ($km >= $minKm && $litres > 0) ? round($litres / $km * 100, 1) : null,
                'seuil_pax' => $seuilPax,
                'seuil_pct' => ($seuilPax && $moyPlaces > 0) ? round($seuilPax / $moyPlaces * 100, 1) : null,
                'amortissable' => $v->estAmortissable(),
                'prix' => (int) $v->prix_acquisition,
                'amort_cumule' => $v->amortissementCumule($fin),
                'vnc' => $v->valeurNetteComptable($fin),
                'fin_amortissement' => $v->finAmortissement()?->format('d/m/Y'),
                'age' => $v->ageAns(),
                'lignes' => $parLigne,
                'chauffeurs' => $parChauffeur,
                'conseils' => [],
            ];

            $car['verdict'] = self::verdict($car);
            $cars[] = $car;
        }

        $flotte = self::totauxFlotte($cars);

        $rendements = collect($lignes)->groupBy('route_id')->map(fn ($g, $rid) => [
            'route' => $libelleRoute((int) $rid),
            'voyages' => $g->count(),
            'recette_moyenne' => (int) round($g->sum('recettes') / $g->count()),
        ])->filter(fn ($r) => $r['voyages'] >= 3)->sortByDesc('recette_moyenne')->values()->all();

        foreach ($cars as $i => $car) {
            $cars[$i]['conseils'] = self::conseils($car, $vehicules[$car['id']], $vehicules, $flotte, $rendements);
        }

        usort($cars, fn ($a, $b) => $b['resultat'] <=> $a['resultat']);

        // Recettes de billets sur des départs où aucun car n'est enregistré : elles ne comptent pour aucun car.
        $cles = collect($lignes)->map(fn ($l) => $l['voyage']->cle())->unique()->all();
        $non = $billets->reject(fn ($v, $k) => in_array($k, $cles, true));

        $resultat['cars'] = $cars;
        $resultat['flotte'] = $flotte;
        $resultat['non_rattache'] = ['montant' => (int) $non->sum('montant'), 'departs' => $non->count()];

        return $resultat;
    }

    /** Mesures à prendre pour un car, du plus urgent au moins urgent. */
    private static function conseils(array $c, Vehicule $v, Collection $vehicules, array $flotte, array $rendements): array
    {
        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ').' FCFA';
        $pct = fn ($n) => number_format((float) $n, 1, ',', ' ').' %';
        $out = [];
        $add = function (string $niveau, string $titre, string $texte) use (&$out) {
            $out[] = ['niveau' => $niveau, 'titre' => $titre, 'texte' => $texte];
        };

        // Documents et vidange
        foreach ($v->documents() as $d) {
            if ($d['jours'] < 0) {
                $add('danger', $d['libelle'].' expiré(e)', 'Expiré depuis '.abs($d['jours']).' jour(s) : le car ne doit pas circuler sans document valide.');
            } elseif ($d['jours'] <= (int) config('flotte.alerte_jours', 30)) {
                $add('warning', $d['libelle'].' à renouveler', 'Expire dans '.$d['jours'].' jour(s), le '.$d['date']->format('d/m/Y').'.');
            }
        }

        if ($v->km_prochaine_vidange && $v->km_actuel >= $v->km_prochaine_vidange - (int) config('flotte.vidange_marge_km', 500)) {
            $add('warning', 'Vidange à prévoir', 'Le car est à '.number_format($v->km_actuel, 0, ',', ' ').' km, la vidange est prévue à '.number_format($v->km_prochaine_vidange, 0, ',', ' ').' km.');
        }

        if ($c['voyages'] === 0) {
            $add('info', 'Aucun voyage sur la période', 'Aucun départ n\'est rattaché à ce car. Programmez ses voyages (Flotte, Voyages des cars) pour calculer sa rentabilité.');

            return $out;
        }

        $min = (int) config('flotte.min_voyages_verdict', 5);

        if ($c['voyages'] < $min) {
            $add('info', 'Verdict provisoire', 'Seulement '.$c['voyages'].' voyage(s) sur la période (minimum '.$min.') : élargissez la période avant de conclure.');
        }

        if ($c['a_cloturer'] > 0) {
            $add('warning', $c['a_cloturer'].' voyage(s) à clôturer', 'Leurs kilomètres, le carburant et les ventes du guichet ne sont pas saisis : le résultat est surestimé tant qu\'ils ne sont pas clôturés.');
        }

        if ($c['charges'] === 0) {
            $add('warning', 'Aucune dépense enregistrée', 'Le résultat est surestimé : saisissez le carburant, l\'entretien et le chauffeur.');
        }

        if (! $c['amortissable']) {
            $add('info', 'Amortissement non compté', 'Le prix d\'achat, la date et la durée d\'amortissement ne sont pas renseignés sur la fiche du car.');
        }

        // Résultat
        if ($c['resultat'] < 0 && $c['recettes'] > 0) {
            if ($c['resultat_avant_amort'] >= 0) {
                $add('danger', 'Ne finance pas son remplacement', 'Le car couvre ses dépenses d\'exploitation mais pas son amortissement : il manque '.$fmt(-$c['resultat']).' sur la période. Il s\'use plus vite qu\'il ne rapporte.');
            } else {
                $add('danger', 'Déficit de '.$fmt(-$c['resultat']), 'Ses dépenses dépassent ses recettes, même avant amortissement.');
            }

            $premier = collect($c['par_groupe'])->sortDesc();

            if ($premier->isNotEmpty() && $c['charges'] > 0) {
                $add('info', 'Premier poste de dépense', (DepenseVehicule::GROUPES[$premier->keys()->first()] ?? '').' : '.$pct($premier->first() / $c['charges'] * 100).' des dépenses.');
            }
        }

        // Seuil de rentabilité
        if ($c['seuil_pax'] && $c['pax_par_voyage'] !== null && $c['resultat'] < $c['recettes'] * 0.1) {
            $add('info', 'Seuil de rentabilité', 'Pour couvrir ses coûts, ce car doit transporter environ '.$c['seuil_pax'].' passagers par voyage ('.$pct($c['seuil_pct']).' des places). Il en transporte '.number_format($c['pax_par_voyage'], 1, ',', ' ').'.');
        }

        // Remplissage
        $bas = (float) config('flotte.remplissage_bas', 50);
        $haut = (float) config('flotte.remplissage_haut', 90);

        if ($c['remplissage'] !== null && $c['remplissage'] < $bas) {
            $pires = collect($c['lignes'])->filter(fn ($l) => $l['remplissage'] !== null && $l['remplissage'] < $bas)->take(2)
                ->map(fn ($l) => $l['route'].' à '.$l['heure'].' ('.$pct($l['remplissage']).')')->implode(' ; ');

            $petit = $vehicules->where('compagnie_id', $v->compagnie_id)->where('statut', 'actif')
                ->filter(fn ($x) => $x->id !== $v->id && $x->places < $v->places)->sortByDesc('places')->first();

            $add('warning', 'Remplissage faible : '.$pct($c['remplissage']), 'Départs les moins remplis : '.$pires.'. Regroupez ces horaires ou affectez-y un car plus petit'
                .($petit ? ' (par exemple '.$petit->immatriculation.', '.$petit->places.' places)' : '').'.');
        } elseif ($c['remplissage'] !== null && $c['remplissage'] > 100) {
            $add('warning', 'Plus de passagers que de places', 'Le remplissage dépasse 100 % : vérifiez les places du car, les ventes du guichet saisies ou l\'affectation des cars sur ces départs.');
        }

        $pleins = collect($c['lignes'])->filter(fn ($l) => $l['remplissage'] !== null && $l['remplissage'] >= $haut)->sortByDesc('remplissage')->take(2);

        if ($pleins->isNotEmpty()) {
            $add('success', 'Départs presque pleins', $pleins->map(fn ($l) => $l['route'].' à '.$l['heure'].' ('.$pct($l['remplissage']).')')->implode(' ; ')
                .'. Ajoutez un départ ou affectez un car plus grand : la demande est là.');
        }

        // Carburant
        $ecart = (float) config('flotte.ecart_carburant', 15);

        if ($c['l100'] !== null && $flotte['l100'] && $c['l100'] > $flotte['l100'] * (1 + $ecart / 100)) {
            $add('warning', 'Consommation élevée', number_format($c['l100'], 1, ',', ' ').' L/100 km, soit '.$pct(($c['l100'] / $flotte['l100'] - 1) * 100).' au-dessus de la moyenne de votre flotte ('.number_format($flotte['l100'], 1, ',', ' ').'). Contrôlez l\'état du moteur, la pression des pneus, la conduite et les justificatifs des pleins.');
        }

        $conso = collect($c['chauffeurs'])->filter(fn ($x) => $x['l100'] !== null);

        if ($conso->count() > 1 && $conso->max('l100') > $conso->min('l100') * 1.2) {
            $meilleur = $conso->sortBy('l100')->first();
            $pire = $conso->sortByDesc('l100')->first();
            $add('info', 'Consommation selon le chauffeur', $pire['nom'].' consomme '.number_format($pire['l100'], 1, ',', ' ').' L/100 km avec ce car, contre '.number_format($meilleur['l100'], 1, ',', ' ').' pour '.$meilleur['nom'].'. Une conduite plus économe est possible.');
        }

        // Entretien et âge
        $entretien = (int) ($c['par_groupe']['entretien'] ?? 0);

        if ($c['recettes'] > 0 && $entretien / $c['recettes'] * 100 > (float) config('flotte.entretien_part_max', 15)) {
            $texte = 'L\'entretien et les réparations coûtent '.$fmt($entretien).', soit '.$pct($entretien / $c['recettes'] * 100).' des recettes (seuil indicatif : '.config('flotte.entretien_part_max', 15).' %).';

            if ($c['age'] !== null && $c['age'] >= (int) config('flotte.age_remplacement_ans', 10)) {
                $add('danger', 'Étudier son remplacement', $texte.' Le car a '.$c['age'].' ans et sa valeur nette est de '.$fmt($c['vnc']).' : comparez le coût de ses réparations à celui d\'un car plus récent.');
            } else {
                $add('warning', 'Entretien coûteux', $texte.' Recherchez la panne récurrente ou comparez avec un autre garage.');
            }
        }

        // Utilisation
        $seuilUse = (float) config('flotte.sous_utilisation', 60);

        if ($flotte['nb_cars'] > 1 && $flotte['voyages_moyen'] > 0 && $c['voyages'] < $flotte['voyages_moyen'] * $seuilUse / 100) {
            $add('warning', 'Car peu utilisé', 'Il fait '.$c['voyages'].' voyage(s) contre '.number_format($flotte['voyages_moyen'], 1, ',', ' ').' en moyenne : ses charges fixes (amortissement, assurance) sont mal absorbées. Affectez-lui davantage de départs, ou envisagez de le louer ou de le revendre.');
        }

        // Meilleure ligne
        $meilleure = $rendements[0] ?? null;
        $moyenne = $c['voyages'] > 0 ? $c['recettes'] / $c['voyages'] : 0;

        if ($meilleure && $moyenne < $meilleure['recette_moyenne'] * 0.75) {
            $add('info', 'Ligne plus rentable dans votre flotte', $meilleure['route'].' rapporte en moyenne '.$fmt($meilleure['recette_moyenne']).' par voyage, contre '.$fmt((int) $moyenne).' pour ce car. Affectez-le en priorité à cette ligne si la demande le permet (comparez la durée des trajets).');
        }

        // Amortissement terminé
        if ($c['amortissable'] && $v->finAmortissement() && $v->finAmortissement()->lt(today())) {
            $add('info', 'Amortissement terminé', 'Le car est entièrement amorti depuis le '.$v->finAmortissement()->format('d/m/Y').' : il n\'a plus de charge d\'amortissement, mais prévoyez dès maintenant de quoi financer son remplacement.');
        }

        if ($c['verdict'] === 'rentable' && ! collect($out)->contains(fn ($x) => in_array($x['niveau'], ['danger', 'warning'], true))) {
            $add('success', 'Car rentable', 'Marge de '.$pct($c['marge']).' sur la période. Gardez ses horaires et son entretien, et surveillez la consommation.');
        }

        usort($out, fn ($a, $b) => ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3][$a['niveau']] <=> ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3][$b['niveau']]);

        return $out;
    }
}
