<?php

namespace App\Services\Personnel;

use App\Models\AvanceEmploye;
use App\Models\BulletinPaie;
use App\Models\ContratEmploye;
use App\Models\Employe;
use App\Models\PeriodePaie;
use App\Models\Presence;
use App\Models\RemboursementAvance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Calcul de la paie : salaire, retenues d'absence, heures supplémentaires, CNSS, IUTS, avances.
 * Tous les taux viennent de config/personnel.php.
 */
class CalculPaie
{
    private static function r(float $v): int
    {
        return (int) round($v);
    }

    /**
     * Impôt unique sur les traitements et salaires.
     * Base = brut imposable − CNSS salarié − abattement forfaitaire (25 %, ou 20 % pour les cadres),
     * puis barème progressif et réduction pour charges de famille.
     */
    public static function iuts(int $brutImposable, int $cnssSalarie, int $charges, ?int $salaireBase = null, bool $cadre = false): int
    {
        $pct = (float) ($cadre ? config('personnel.iuts.abattement_cadre_pct', 20) : config('personnel.iuts.abattement_pct', 0));
        $sur = config('personnel.iuts.abattement_sur', 'salaire_base');

        $assietteAbattement = ($sur === 'salaire_base' && $salaireBase !== null) ? $salaireBase : ($brutImposable - $cnssSalarie);
        $abattement = (int) floor(max(0, $assietteAbattement) * $pct / 100);

        $base = max(0, $brutImposable - $cnssSalarie - $abattement);
        $base = (int) (floor($base / 100) * 100);

        $impot = 0.0;
        $precedent = 0;

        foreach (config('personnel.iuts.tranches') as [$plafond, $taux]) {
            if ($base <= $precedent) {
                break;
            }

            $haut = $plafond === null ? $base : min($base, $plafond);
            $impot += ($haut - $precedent) * $taux / 100;
            $precedent = $plafond ?? $base;
        }

        $charges = min($charges, (int) config('personnel.iuts.max_charges', 4));
        $reduction = (float) (config('personnel.iuts.reductions')[$charges] ?? 0);

        return self::r($impot * (1 - $reduction / 100));
    }

    /**
     * Quotité cessible : montant maximal retenu chaque mois pour rembourser une avance,
     * calculé sur l'assiette (salaire après déductions légales) selon les tranches de config/personnel.php.
     */
    public static function quotiteCessible(int $assiette): int
    {
        $assiette = max(0, $assiette);
        $palier = config('personnel.avances.mode', 'progressif') === 'palier';
        $total = 0.0;
        $precedent = 0;

        foreach (config('personnel.avances.tranches', []) as [$plafond, $taux]) {
            if ($palier) {
                if ($plafond === null || $assiette <= $plafond) {
                    return (int) floor($assiette * $taux / 100);
                }

                continue;
            }

            if ($assiette <= $precedent) {
                break;
            }

            $haut = $plafond === null ? $assiette : min($assiette, $plafond);
            $total += ($haut - $precedent) * $taux / 100;
            $precedent = $plafond ?? $assiette;
        }

        return (int) floor($total);
    }

    /** Éléments fixes du contrat : liste, total, part soumise à la CNSS, part imposable. */
    private static function elementsFixes(ContratEmploye $c, float $ratio): array
    {
        $liste = [];
        $total = $cnss = $iuts = 0;

        foreach ($c->elements_fixes ?? [] as $el) {
            $montant = self::r(((int) ($el['montant'] ?? 0)) * $ratio);

            if ($montant <= 0) {
                continue;
            }

            $soumisCnss = (bool) ($el['soumis_cnss'] ?? true);
            $soumisIuts = (bool) ($el['soumis_iuts'] ?? true);

            $total += $montant;
            $cnss += $soumisCnss ? $montant : 0;
            $iuts += $soumisIuts ? $montant : 0;
            $liste[] = ['libelle' => (string) ($el['libelle'] ?? 'Indemnité'), 'montant' => $montant, 'cnss' => $soumisCnss, 'iuts' => $soumisIuts];
        }

        return ['liste' => $liste, 'total' => $total, 'cnss' => $cnss, 'iuts' => $iuts];
    }

    /** CNSS salarié, IUTS et bases associées. */
    private static function deductionsLegales(int $baseNette, array $el, int $hs, int $primes, int $charges, bool $cadre): array
    {
        $assietteCnss = min($baseNette + $el['cnss'] + $hs + $primes, (int) config('personnel.cnss.plafond'));
        $cnss = self::r($assietteCnss * (float) config('personnel.cnss.salarie_pct') / 100);

        $brutImposable = $baseNette + $el['iuts'] + $hs + $primes;
        $iuts = self::iuts($brutImposable, $cnss, $charges, $baseNette, $cadre);

        return ['assiette_cnss' => $assietteCnss, 'cnss' => $cnss, 'brut_imposable' => $brutImposable, 'iuts' => $iuts];
    }

    /** Estimation de la quotité cessible mensuelle d'un employé selon son contrat (mois complet, sans heures sup.). */
    public static function quotiteCessibleContrat(Employe $e, ContratEmploye $c): int
    {
        $base = $c->mode === 'journalier' ? (int) $c->salaire_base * 26 : (int) $c->salaire_base;
        $el = self::elementsFixes($c, 1.0);
        $d = self::deductionsLegales($base, $el, 0, 0, $e->chargesFamille(), (bool) $c->cadre);

        return self::quotiteCessible($base + $el['total'] - $d['cnss'] - $d['iuts']);
    }

    /** Calcule un bulletin (sans l'enregistrer). */
    public static function calculer(Employe $e, ContratEmploye $c, PeriodePaie $p, int $primesVariables = 0, int $autresRetenues = 0): array
    {
        $debut = $p->debut();
        $fin = $debut->copy()->endOfMonth()->startOfDay();

        $presences = Presence::query()->where('employe_id', $e->id)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get();

        $compte = $presences->countBy('statut');
        $nbStatuts = fn (array $statuts) => (int) collect($statuts)->sum(fn ($s) => $compte[$s] ?? 0);

        $nonPayes = $nbStatuts(config('personnel.paie.statuts_non_payes', []));
        $joursTravailles = $nbStatuts(['present', 'retard', 'mission']);
        $minutesRetard = (int) $presences->sum('minutes_retard');
        $minutesHs = (int) $presences->sum('heures_sup_minutes');

        $joursMois = max(1, (int) config('personnel.paie.jours_mois', 30));
        $mensuel = $c->mode !== 'journalier';

        // Prorata de la première ou de la dernière période d'emploi.
        $ratio = 1.0;

        if ($mensuel) {
            $a = $debut->copy();
            $b = $fin->copy();

            foreach ([$e->date_embauche, $c->date_debut] as $d) {
                if ($d && $d->gt($a)) {
                    $a = $d->copy();
                }
            }

            foreach ([$e->date_sortie, $c->date_fin] as $d) {
                if ($d && $d->lt($b)) {
                    $b = $d->copy();
                }
            }

            $jEmploi = $b->lt($a) ? 0 : (int) $a->diffInDays($b) + 1;
            $ratio = $jEmploi >= $debut->daysInMonth ? 1.0 : $jEmploi / $debut->daysInMonth;
        }

        if ($mensuel) {
            $base = self::r($c->salaire_base * $ratio);
            $retenue = self::r($c->salaire_base / $joursMois * $nonPayes);
            $tauxHoraire = $c->salaire_base / max(1, $c->heures_hebdo * 52 / 12);
        } else {
            $base = (int) $c->salaire_base * $joursTravailles;
            $retenue = 0;
            $tauxHoraire = $c->salaire_base / max(1, (int) config('personnel.paie.heures_jour', 8));
        }

        $baseNette = max(0, $base - $retenue);

        $el = self::elementsFixes($c, $mensuel ? $ratio : 1.0);
        $primesFixes = $el['total'];

        $hsMontant = self::r(($minutesHs / 60) * $tauxHoraire * (1 + (float) config('personnel.paie.majoration_hs_pct', 15) / 100));

        $brut = $baseNette + $primesFixes + $hsMontant + $primesVariables;

        $d = self::deductionsLegales($baseNette, $el, $hsMontant, $primesVariables, $e->chargesFamille(), (bool) $c->cadre);
        $assietteCnss = $d['assiette_cnss'];
        $cnssSalarie = $d['cnss'];
        $brutImposable = $d['brut_imposable'];
        $iuts = $d['iuts'];

        // Avances : la retenue du mois ne dépasse pas la quotité cessible (salaire après CNSS et IUTS).
        $quotite = self::quotiteCessible($brut - $cnssSalarie - $iuts);
        $capacite = $quotite;
        $plan = [];
        $retenueAvance = 0;
        $alertes = [];
        $plafonne = false;

        $avances = AvanceEmploye::query()->where('employe_id', $e->id)->where('statut', 'en_cours')
            ->where('reste_du', '>', 0)->whereDate('debut_remboursement', '<=', $fin->toDateString())->orderBy('date_octroi')->get();

        foreach ($avances as $av) {
            $du = min((int) $av->reste_du, (int) $av->mensualite);
            $mt = min($du, $capacite);

            if ($mt < $du) {
                $plafonne = true;
            }

            if ($mt > 0) {
                $plan[] = ['avance_id' => $av->id, 'montant' => $mt];
                $retenueAvance += $mt;
                $capacite -= $mt;
            }
        }

        if ($plafonne) {
            $alertes[] = 'Retenue d\'avance plafonnée à la quotité cessible ('.number_format($quotite, 0, ',', ' ').' FCFA) : le solde est reporté.';
        }

        $net = $brut - $cnssSalarie - $iuts - $retenueAvance - $autresRetenues;

        if ($net < 0) {
            $alertes[] = 'Les retenues dépassent le salaire : net ramené à 0.';
            $net = 0;
        }

        $cnssEmployeur = self::r($assietteCnss * (float) config('personnel.cnss.employeur_pct') / 100);
        $tpa = self::r($brut * (float) config('personnel.paie.tpa_pct', 0) / 100);

        return [
            'jours_travailles' => $joursTravailles,
            'jours_non_payes' => $nonPayes,
            'minutes_retard' => $minutesRetard,
            'heures_sup_minutes' => $minutesHs,
            'salaire_base' => $base,
            'retenue_absences' => $retenue,
            'primes_fixes' => $primesFixes,
            'heures_sup_montant' => $hsMontant,
            'primes_variables' => $primesVariables,
            'brut' => $brut,
            'base_cnss' => $assietteCnss,
            'cnss_salarie' => $cnssSalarie,
            'brut_imposable' => $brutImposable,
            'iuts' => $iuts,
            'retenue_avance' => $retenueAvance,
            'autres_retenues' => $autresRetenues,
            'net_a_payer' => $net,
            'cnss_employeur' => $cnssEmployeur,
            'tpa' => $tpa,
            'cout_employeur' => $brut + $cnssEmployeur + $tpa,
            'detail' => [
                'elements' => $el['liste'],
                'quotite_cessible' => $quotite,
                'cadre' => (bool) $c->cadre,
                'avances' => $plan,
                'alertes' => $alertes,
                'charges_famille' => $e->chargesFamille(),
                'mode' => $c->mode,
                'taux_journalier' => $mensuel ? null : (int) $c->salaire_base,
            ],
        ];
    }

    /**
     * Calcule (ou recalcule) tous les bulletins d'une période en brouillon.
     *
     * @return array{bulletins: int, sans_contrat: array<int, string>}
     *
     * @throws \DomainException
     */
    public static function generer(PeriodePaie $p, User $acteur): array
    {
        abort_unless(AccesPersonnel::paieGestion($acteur), 403);

        if ($p->statut !== 'brouillon') {
            throw new \DomainException('Seule une période en brouillon peut être recalculée.');
        }

        $debut = $p->debut();
        $fin = $debut->copy()->endOfMonth()->startOfDay();

        $n = 0;
        $gardes = [];
        $sansContrat = [];

        $employes = Employe::query()->visiblePour($acteur)->where('compagnie_id', $p->compagnie_id)->get();

        foreach ($employes as $e) {
            if ($e->date_embauche && $e->date_embauche->gt($fin)) {
                continue;
            }

            if ($e->date_sortie && $e->date_sortie->lt($debut)) {
                continue;
            }

            $contrat = ContratEmploye::query()->where('employe_id', $e->id)
                ->whereDate('date_debut', '<=', $fin->toDateString())
                ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut->toDateString()))
                ->orderByDesc('date_debut')->first();

            if (! $contrat) {
                $sansContrat[] = $e->nomComplet();

                continue;
            }

            $existant = BulletinPaie::where('periode_paie_id', $p->id)->where('employe_id', $e->id)->first();

            $calc = self::calculer($e, $contrat, $p, (int) ($existant?->primes_variables ?? 0), (int) ($existant?->autres_retenues ?? 0));

            BulletinPaie::updateOrCreate(
                ['periode_paie_id' => $p->id, 'employe_id' => $e->id],
                $calc + ['compagnie_id' => $p->compagnie_id, 'contrat_id' => $contrat->id]
            );

            $gardes[] = $e->id;
            $n++;
        }

        BulletinPaie::where('periode_paie_id', $p->id)->whereNotIn('employe_id', $gardes)->delete();
        $p->forceFill(['genere_le' => now()])->save();

        return ['bulletins' => $n, 'sans_contrat' => $sansContrat];
    }

    /** Recalcule un seul bulletin (après saisie de primes ou de retenues). */
    public static function recalculer(BulletinPaie $b): void
    {
        abort_unless(AccesPersonnel::paieGestion(auth()->user()), 403);

        $p = $b->periode;

        if ($p->statut !== 'brouillon') {
            throw new \DomainException('La période est validée : le bulletin n\'est plus modifiable.');
        }

        $contrat = $b->contrat ?? ContratEmploye::findOrFail($b->contrat_id);

        $calc = self::calculer($b->employe, $contrat, $p, (int) $b->primes_variables, (int) $b->autres_retenues);
        $b->forceFill($calc)->save();
    }

    /**
     * Valide la période : recalcul final, application des remboursements d'avances, verrouillage.
     *
     * @throws \DomainException
     */
    public static function valider(PeriodePaie $p, User $acteur): void
    {
        DB::transaction(function () use ($p, $acteur) {
            $p = PeriodePaie::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();

            $res = self::generer($p, $acteur);

            if ($res['bulletins'] === 0) {
                throw new \DomainException('Aucun bulletin à valider : vérifiez que les employés ont un contrat actif sur ce mois.');
            }

            foreach (BulletinPaie::where('periode_paie_id', $p->id)->get() as $b) {
                foreach ($b->detail['avances'] ?? [] as $ligne) {
                    $av = AvanceEmploye::query()->whereKey($ligne['avance_id'])->lockForUpdate()->first();

                    if (! $av) {
                        continue;
                    }

                    $mt = min((int) $ligne['montant'], (int) $av->reste_du);
                    RemboursementAvance::create(['avance_id' => $av->id, 'bulletin_id' => $b->id, 'montant' => $mt]);

                    $reste = (int) $av->reste_du - $mt;
                    $av->forceFill(['reste_du' => $reste, 'statut' => $reste <= 0 ? 'solde' : 'en_cours'])->save();
                }

                $b->forceFill(['statut_paiement' => 'a_payer'])->save();
            }

            $p->forceFill(['statut' => 'validee', 'valide_par' => $acteur->id, 'valide_le' => now()])->save();
        });
    }

    /** Rouvre une période validée (aucun bulletin payé) : les remboursements d'avances sont annulés. */
    public static function rouvrir(PeriodePaie $p): void
    {
        abort_unless(AccesPersonnel::paieGestion(auth()->user()), 403);

        DB::transaction(function () use ($p) {
            $p = PeriodePaie::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();

            if ($p->statut !== 'validee') {
                throw new \DomainException('Seule une période validée et non payée peut être rouverte.');
            }

            if (BulletinPaie::where('periode_paie_id', $p->id)->where('statut_paiement', 'paye')->exists()) {
                throw new \DomainException('Des bulletins ont déjà été payés : la période ne peut plus être rouverte.');
            }

            $ids = BulletinPaie::where('periode_paie_id', $p->id)->pluck('id');

            foreach (RemboursementAvance::whereIn('bulletin_id', $ids)->get() as $r) {
                $av = AvanceEmploye::whereKey($r->avance_id)->lockForUpdate()->first();
                $av?->forceFill(['reste_du' => (int) $av->reste_du + (int) $r->montant, 'statut' => 'en_cours'])->save();
                $r->delete();
            }

            $p->forceFill(['statut' => 'brouillon', 'valide_par' => null, 'valide_le' => null])->save();
        });
    }

    public static function payerBulletin(BulletinPaie $b, ?string $reference = null): void
    {
        abort_unless(AccesPersonnel::paieGestion(auth()->user()), 403);

        if ($b->periode->statut === 'brouillon') {
            throw new \DomainException('Validez la période avant de payer.');
        }

        $b->forceFill([
            'statut_paiement' => 'paye', 'paye_le' => now(),
            'mode_paiement' => $b->employe->mode_paiement, 'reference_paiement' => $reference,
        ])->save();

        $p = $b->periode;

        if (! BulletinPaie::where('periode_paie_id', $p->id)->where('statut_paiement', '!=', 'paye')->exists()) {
            $p->forceFill(['statut' => 'payee', 'paye_le' => now()])->save();
        }
    }

    public static function payerPeriode(PeriodePaie $p): void
    {
        if ($p->statut === 'brouillon') {
            throw new \DomainException('Validez la période avant de payer.');
        }

        BulletinPaie::where('periode_paie_id', $p->id)->where('statut_paiement', '!=', 'paye')->get()
            ->each(fn (BulletinPaie $b) => self::payerBulletin($b));
    }
}
