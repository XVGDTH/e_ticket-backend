<?php

namespace App\Services\ControleGestion;

use App\Models\CgBudget;
use App\Models\CgBudgetLigne;
use App\Models\CgParametre;
use App\Models\ChargeStructure;
use App\Models\Compagnie;
use App\Models\RouteTransport;
use App\Models\User;
use App\Services\Billetterie;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Budget construit à partir du plan de transport : pour chaque mois et chaque ligne active,
 * départs (horaires × jours de départ), kilomètres (distance de la ligne), places, remplissage cible, tarif,
 * puis coûts standards. Les charges de gare, de siège et d'atelier viennent des charges de structure saisies.
 */
class BudgetService
{
    /** Standards observés sur les 3 derniers mois entiers : sert de valeurs de départ, modifiables. */
    public static function standardsHistoriques(int $cid): array
    {
        $fin = today()->copy()->subMonthNoOverflow()->endOfMonth();
        $debut = today()->copy()->subMonthsNoOverflow(3)->startOfMonth();

        $a = ModeleCouts::analyser($cid, $debut, $fin);
        $t = $a['total'];
        $km = (int) $t['km'];
        $voyages = (int) $t['voyages'];
        $paie = collect($a['lignes'])->where('equipage_paie', true);
        $heures = (float) $paie->sum('heures');
        $fixes = (int) $t['fixes_car'] + (int) ($a['hors_departs']['immobilisation'] ?? 0);

        return [
            'remplissage_cible_pct' => $t['remplissage'] !== null ? round($t['remplissage'], 1) : null,
            'cout_km_maintenance' => $km > 0 ? (int) round($t['maintenance'] / $km) : null,
            'cout_km_pneus' => $km > 0 ? (int) round($t['pneus'] / $km) : null,
            'frais_route_depart' => $voyages > 0 ? (int) round($t['route'] / $voyages) : null,
            'cout_horaire_equipage' => $heures > 0 ? (int) round($paie->sum('equipage') / $heures) : null,
            'fixes_car_mensuel' => $fixes > 0 ? (int) round($fixes / 3) : null,
        ];
    }

    /** @return array{budget: CgBudget, sans_distance: array<int,string>, sans_duree: array<int,string>} */
    public static function generer(int $cid, array $h, User $acteur): array
    {
        $compagnie = Compagnie::findOrFail($cid);
        $p = CgParametre::pour($cid);
        $annee = (int) $h['annee'];

        $routes = RouteTransport::with(['horaires', 'gareDepart', 'villeArrivee'])
            ->where('compagnie_id', $cid)->where('actif', true)->get();

        $structure = ChargeStructure::query()->where('compagnie_id', $cid)
            ->whereDate('periode_debut', '<=', $annee.'-12-31')
            ->whereDate('periode_fin', '>=', $annee.'-01-01')->get();

        $places = Billetterie::placesBusDe($compagnie);
        $sansDistance = [];
        $sansDuree = [];

        $budget = DB::transaction(function () use ($cid, $h, $acteur, $p, $annee, $routes, $structure, $places, $compagnie, &$sansDistance, &$sansDuree) {
            $version = (int) CgBudget::where('compagnie_id', $cid)->where('annee', $annee)->lockForUpdate()->max('version') + 1;

            $budget = CgBudget::create([
                'compagnie_id' => $cid,
                'annee' => $annee,
                'version' => $version,
                'libelle' => trim($h['libelle']),
                'statut' => 'brouillon',
                'remplissage_cible_pct' => (float) $h['remplissage_cible_pct'],
                'cout_km_maintenance' => (int) $h['cout_km_maintenance'],
                'cout_km_pneus' => (int) $h['cout_km_pneus'],
                'frais_route_depart' => (int) $h['frais_route_depart'],
                'cout_horaire_equipage' => (int) $h['cout_horaire_equipage'],
                'fixes_car_mensuel' => (int) $h['fixes_car_mensuel'],
                'norme_l100' => $p['norme_l100'],
                'prix_litre' => $p['prix_litre'],
                'commission_paiement_pct' => $p['commission_paiement_pct'],
                'note' => filled($h['note'] ?? null) ? trim($h['note']) : null,
                'cree_par' => $acteur->id,
            ]);

            $rows = [];

            foreach (range(1, 12) as $mois) {
                $debut = Carbon::create($annee, $mois, 1)->startOfDay();
                $fin = $debut->copy()->endOfMonth()->startOfDay();

                $nbJours = array_fill(1, 7, 0);

                for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
                    $nbJours[$d->dayOfWeekIso]++;
                }

                $mensuel = [];

                foreach ($routes as $r) {
                    $departs = 0;

                    foreach ($r->horaires as $hor) {
                        foreach ($nbJours as $iso => $n) {
                            if ($n > 0 && Billetterie::jourAutorise($hor->jours_semaine, $iso)) {
                                $departs += $n;
                            }
                        }
                    }

                    if ($departs === 0) {
                        continue;
                    }

                    $libelle = ($r->gareDepart?->quartier ?? '?').' → '.($r->villeArrivee?->nom ?? '?');
                    $duree = self::heures($r->duree_max);

                    if (! $r->distance_km) {
                        $sansDistance[$r->id] = $libelle;
                    }

                    if ($duree === null) {
                        $sansDuree[$r->id] = $libelle;
                    }

                    $km = $departs * (int) $r->distance_km;
                    $sieges = $departs * $places;
                    $pax = (int) round($sieges * $budget->remplissage_cible_pct / 100);
                    $recettes = $pax * (int) $r->prix;
                    $quota = Billetterie::quotaAppDe($r->gareDepart, $compagnie);

                    $mensuel[$r->id] = [
                        'budget_id' => $budget->id,
                        'mois' => $mois,
                        'route_transport_id' => $r->id,
                        'departs' => $departs,
                        'km' => $km,
                        'sieges' => $sieges,
                        'pax' => $pax,
                        'recettes' => $recettes,
                        'commissions' => (int) round($recettes * $quota / 100 * $p['commission_paiement_pct'] / 100),
                        'carburant' => (int) round($km * $p['norme_l100'] / 100 * $p['prix_litre']),
                        'equipage' => $duree ? (int) round($departs * $duree * $budget->cout_horaire_equipage) : 0,
                        'maintenance' => (int) round($km * $budget->cout_km_maintenance),
                        'pneus' => (int) round($km * $budget->cout_km_pneus),
                        'frais_route' => (int) round($departs * $budget->frais_route_depart),
                        'fixes_car' => 0,
                        'gare' => 0,
                        'siege' => 0,
                    ];
                }

                if ($mensuel === []) {
                    continue;
                }

                self::repartirDans($mensuel, 'fixes_car', (float) $budget->fixes_car_mensuel, array_keys($mensuel), 'km');

                foreach ($structure as $c) {
                    $m = $c->partPour($debut, $fin);

                    if ($m <= 0) {
                        continue;
                    }

                    $ids = array_keys($mensuel);

                    if ($c->gare_id) {
                        $ids = array_values(array_intersect($ids, $routes->where('gare_depart_id', $c->gare_id)->pluck('id')->all()));
                    }

                    if ($ids !== []) {
                        self::repartirDans($mensuel, $c->niveau === 'gare' ? 'gare' : 'siege', $m, $ids, $c->cle);
                    }
                }

                array_push($rows, ...array_values($mensuel));
            }

            foreach (array_chunk($rows, 200) as $lot) {
                CgBudgetLigne::insert($lot);
            }

            return $budget;
        });

        return ['budget' => $budget, 'sans_distance' => $sansDistance, 'sans_duree' => $sansDuree];
    }

    /** Répartit un montant sur des lignes selon une clé (le dernier absorbe l'arrondi). */
    private static function repartirDans(array &$mensuel, string $champ, float $montant, array $ids, string $cle): void
    {
        $poids = [];

        foreach ($ids as $id) {
            $poids[$id] = (float) match ($cle) {
                'voyageurs' => $mensuel[$id]['pax'],
                'km' => $mensuel[$id]['km'],
                'recettes' => $mensuel[$id]['recettes'],
                default => $mensuel[$id]['departs'],
            };
        }

        $total = array_sum($poids);

        if ($total <= 0) {
            $poids = array_fill_keys($ids, 1.0);
            $total = (float) count($ids);
        }

        $reste = (int) round($montant);
        $dernier = array_key_last($poids);

        foreach ($poids as $id => $w) {
            $part = $id === $dernier ? $reste : (int) round($montant * $w / $total);
            $mensuel[$id][$champ] += $part;
            $reste -= $part;
        }
    }

    /** « 5h30 », « 5h » ou « 5,5 » deviennent des heures décimales ; null si illisible. */
    public static function heures(?string $texte): ?float
    {
        $texte = trim((string) $texte);

        if (preg_match('/(\d+)\s*h\s*(\d{1,2})?/i', $texte, $m)) {
            return (int) $m[1] + (int) ($m[2] ?? 0) / 60;
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)$/', $texte, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }
}
