<?php

namespace App\Services;

use App\Models\CptCompte;
use App\Models\CptExercice;
use App\Models\CptJournal;
use App\Models\CptSection;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Compta
{
    private static array $initialisees = [];

    /** Prépare une compagnie : exercice de l'année, journaux et sections analytiques de départ. */
    public static function initialiser(int $compagnieId): void
    {
        if (isset(self::$initialisees[$compagnieId])) {
            return;
        }

        $couvert = CptExercice::where('compagnie_id', $compagnieId)
            ->whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->exists();

        if (! $couvert) {
            $annee = now()->year;

            CptExercice::firstOrCreate(
                ['compagnie_id' => $compagnieId, 'date_debut' => $annee.'-01-01'],
                ['libelle' => 'Exercice '.$annee, 'date_fin' => $annee.'-12-31']
            );
        }

        foreach ([
            ['AC', 'Achats', 'achats'], ['VE', 'Ventes', 'ventes'], ['BQ', 'Banque', 'banque'],
            ['CA', 'Caisse', 'caisse'], ['OD', 'Opérations diverses', 'od'], ['AN', 'À-nouveaux', 'a_nouveau'],
        ] as [$code, $libelle, $type]) {
            CptJournal::firstOrCreate(['compagnie_id' => $compagnieId, 'code' => $code], ['libelle' => $libelle, 'type' => $type]);
        }

        foreach ([
            ['BIL', 'Billetterie'], ['COL', 'Colis et courriers'], ['GUI', 'Ventes au guichet'], ['ADM', 'Administration'],
        ] as [$code, $libelle]) {
            CptSection::firstOrCreate(['compagnie_id' => $compagnieId, 'code' => $code], ['libelle' => $libelle, 'type' => 'activite']);
        }

        self::$initialisees[$compagnieId] = true;
    }

    public static function exercices(int $compagnieId): Collection
    {
        return CptExercice::where('compagnie_id', $compagnieId)->orderByDesc('date_debut')->get();
    }

    public static function exerciceCourant(int $compagnieId): ?CptExercice
    {
        return CptExercice::where('compagnie_id', $compagnieId)
            ->whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->first()
            ?? CptExercice::where('compagnie_id', $compagnieId)->orderByDesc('date_debut')->first();
    }

    public static function exerciceOuvert(int $compagnieId, Carbon $date): ?CptExercice
    {
        return CptExercice::where('compagnie_id', $compagnieId)->where('cloture', false)
            ->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first();
    }

    public static function prochainNumero(int $journalId, Carbon $date): string
    {
        return DB::transaction(function () use ($journalId, $date) {
            $j = DB::table('cpt_journaux')->where('id', $journalId)->lockForUpdate()->first();
            $n = (int) $j->dernier_numero + 1;

            DB::table('cpt_journaux')->where('id', $journalId)->update(['dernier_numero' => $n]);

            return sprintf('%s-%s-%05d', $j->code, $date->format('Y'), $n);
        });
    }

    // ------------------------------------------------------------------ listes

    private static function comptesQuery(int $compagnieId)
    {
        return CptCompte::query()->where('actif', true)
            ->where(fn ($q) => $q->whereNull('compagnie_id')->orWhere('compagnie_id', $compagnieId))
            ->orderBy('numero');
    }

    public static function optionsComptes(int $compagnieId): array
    {
        return self::comptesQuery($compagnieId)->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->numero.' · '.$c->intitule])->all();
    }

    public static function comptesIds(int $compagnieId): array
    {
        return self::comptesQuery($compagnieId)->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    public static function optionsJournaux(int $compagnieId): array
    {
        if (! $compagnieId) {
            return [];
        }

        self::initialiser($compagnieId);

        return CptJournal::where('compagnie_id', $compagnieId)->orderBy('code')->get()
            ->mapWithKeys(fn ($j) => [$j->id => $j->code.' · '.$j->libelle])->all();
    }

    public static function optionsSections(int $compagnieId): array
    {
        if (! $compagnieId) {
            return [];
        }

        self::initialiser($compagnieId);

        return CptSection::where('compagnie_id', $compagnieId)->where('actif', true)->orderBy('code')->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->code.' · '.$s->libelle])->all();
    }

    public static function sectionsIds(int $compagnieId): array
    {
        return CptSection::where('compagnie_id', $compagnieId)->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    // ------------------------------------------------------------------ calculs

    /** Débits, crédits et solde de chaque compte, sur les écritures comptabilisées de l'exercice. */
    public static function soldes(int $compagnieId, int $exerciceId): Collection
    {
        return DB::table('cpt_lignes as l')
            ->join('cpt_ecritures as e', 'e.id', '=', 'l.ecriture_id')
            ->join('cpt_comptes as c', 'c.id', '=', 'l.compte_id')
            ->where('e.compagnie_id', $compagnieId)
            ->where('e.exercice_id', $exerciceId)
            ->where('e.statut', 'comptabilise')
            ->groupBy('c.numero', 'c.intitule')
            ->orderBy('c.numero')
            ->selectRaw('c.numero, c.intitule, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get()
            ->map(fn ($r) => [
                'numero' => (string) $r->numero,
                'intitule' => $r->intitule,
                'debit' => (int) $r->debit,
                'credit' => (int) $r->credit,
                'solde' => (int) $r->debit - (int) $r->credit,
            ]);
    }

    /**
     * Somme des soldes (débit − crédit) des comptes dont le numéro commence par un des préfixes.
     * mode : net, debit (soldes débiteurs seulement) ou credit (soldes créditeurs seulement, en positif).
     */
    public static function somme(Collection $soldes, array $prefixes, string $mode = 'net'): int
    {
        return (int) $soldes
            ->filter(fn ($r) => collect($prefixes)->contains(fn ($p) => str_starts_with($r['numero'], $p)))
            ->sum(fn ($r) => match ($mode) {
                'debit' => max($r['solde'], 0),
                'credit' => max(-$r['solde'], 0),
                default => $r['solde'],
            });
    }

    public static function balance(Collection $soldes): array
    {
        $lignes = $soldes->map(fn ($r) => [
            $r['numero'], $r['intitule'], $r['debit'], $r['credit'], max($r['solde'], 0), max(-$r['solde'], 0),
        ])->values()->all();

        $lignes[] = [
            'TOTAL', '',
            (int) $soldes->sum('debit'), (int) $soldes->sum('credit'),
            (int) $soldes->sum(fn ($r) => max($r['solde'], 0)), (int) $soldes->sum(fn ($r) => max(-$r['solde'], 0)),
        ];

        return $lignes;
    }

    /** Compte de résultat avec soldes intermédiaires de gestion. Produits positifs, charges négatives. */
    public static function compteDeResultat(Collection $s): array
    {
        $v = fn (array $pre) => -self::somme($s, $pre); // crédit − débit

        $ta = $v(['701']);
        $ra = $v(['601']);
        $rb = $v(['6031']);
        $xa = $ta + $ra + $rb;

        $tb = $v(['702', '703', '704']);
        $tc = $v(['705', '706']);
        $td = $v(['707']);
        $xb = $ta + $tb + $tc + $td;

        $te = $v(['73']);
        $tf = $v(['72']);
        $tg = $v(['71']);
        $th = $v(['75']);
        $ti = $v(['781']);
        $rc = $v(['602']);
        $rd = $v(['6032']);
        $re = $v(['604', '605', '608']);
        $rf = $v(['6033']);
        $rg = $v(['61']);
        $rh = $v(['62', '63']);
        $ri = $v(['64']);
        $rj = $v(['65']);
        $xc = $xb + $ra + $rb + $te + $tf + $tg + $th + $ti + $rc + $rd + $re + $rf + $rg + $rh + $ri + $rj;

        $rk = $v(['66']);
        $xd = $xc + $rk;

        $tj = $v(['791', '798']);
        $rl = $v(['681', '691']);
        $xe = $xd + $tj + $rl;

        $tk = $v(['77']);
        $tl = $v(['797']);
        $tm = $v(['787']);
        $rm = $v(['67']);
        $rn = $v(['687', '697']);
        $xf = $tk + $tl + $tm + $rm + $rn;
        $xg = $xe + $xf;

        $tn = $v(['82']);
        $to = $v(['84', '86', '88']);
        $ro = $v(['81']);
        $rp = $v(['83', '85']);
        $xh = $tn + $to + $ro + $rp;

        $rq = $v(['87']);
        $rs = $v(['89']);
        $xi = $xg + $xh + $rq + $rs;

        $l = fn (string $libelle, int $montant, bool $solde = false) => ['libelle' => $libelle, 'montant' => $montant, 'solde' => $solde];

        return [
            'resultat' => $xi,
            'chiffre_affaires' => $xb,
            'valeur_ajoutee' => $xc,
            'ebe' => $xd,
            'lignes' => [
                $l('Ventes de marchandises', $ta),
                $l('Achats de marchandises et variation de stocks', $ra + $rb),
                $l('MARGE COMMERCIALE', $xa, true),
                $l('Ventes de produits fabriqués', $tb),
                $l('Travaux et services vendus (billetterie, colis…)', $tc),
                $l('Produits accessoires', $td),
                $l('CHIFFRE D\'AFFAIRES', $xb, true),
                $l('Production stockée et immobilisée', $te + $tf),
                $l('Subventions d\'exploitation, autres produits et transferts de charges', $tg + $th + $ti),
                $l('Achats de matières et fournitures (carburant, pièces…) et variations de stocks', $rc + $rd + $re + $rf),
                $l('Transports', $rg),
                $l('Services extérieurs (entretien, assurances, télécoms, frais bancaires…)', $rh),
                $l('Impôts et taxes', $ri),
                $l('Autres charges', $rj),
                $l('VALEUR AJOUTÉE', $xc, true),
                $l('Charges de personnel', $rk),
                $l('EXCÉDENT BRUT D\'EXPLOITATION', $xd, true),
                $l('Reprises d\'amortissements, provisions et dépréciations', $tj),
                $l('Dotations aux amortissements, provisions et dépréciations', $rl),
                $l('RÉSULTAT D\'EXPLOITATION', $xe, true),
                $l('Revenus financiers et produits assimilés', $tk + $tl + $tm),
                $l('Frais financiers et charges assimilées', $rm + $rn),
                $l('RÉSULTAT FINANCIER', $xf, true),
                $l('RÉSULTAT DES ACTIVITÉS ORDINAIRES', $xg, true),
                $l('Produits hors activités ordinaires (HAO)', $tn + $to),
                $l('Charges hors activités ordinaires (HAO)', $ro + $rp),
                $l('RÉSULTAT HORS ACTIVITÉS ORDINAIRES', $xh, true),
                $l('Participation des travailleurs', $rq),
                $l('Impôts sur le résultat', $rs),
                $l('RÉSULTAT NET', $xi, true),
            ],
        ];
    }

    public static function bilan(Collection $s): array
    {
        $g = fn (array $pre, string $mode = 'net') => self::somme($s, $pre, $mode);
        $resultat = self::compteDeResultat($s)['resultat'];

        $immo = function (string $libelle, array $brut, array $amort) use ($g) {
            $b = $g($brut);
            $a = -$g($amort);

            return ['libelle' => $libelle, 'brut' => $b, 'amort' => $a, 'net' => $b - $a, 'total' => false];
        };

        $tiers = ['40', '41', '42', '43', '44', '45', '46', '47', '48'];
        $treso = ['50', '51', '52', '53', '54', '55', '56', '57', '58'];

        $stocks = $g(['31', '32', '33', '34', '35', '36', '37', '38', '39']);
        $creances = $g($tiers, 'debit') + $g(['49']) - $g(['499']);
        $tresoActif = $g($treso, 'debit') + $g(['59']);

        $capitaux = -$g(['10', '11', '12', '13', '14', '15']) + $resultat;
        $dettesFin = -$g(['16', '17', '18', '19']);
        $passifCirculant = $g($tiers, 'credit') - $g(['499']);
        $tresoPassif = $g($treso, 'credit');

        $actif = [
            $immo('Immobilisations incorporelles', ['21'], ['281', '291']),
            $immo('Immobilisations corporelles (dont cars)', ['22', '23', '24', '25'], ['282', '283', '284', '292', '293', '294']),
            $immo('Immobilisations financières', ['26', '27'], ['296', '297']),
            ['libelle' => 'Stocks et encours', 'brut' => null, 'amort' => null, 'net' => $stocks, 'total' => false],
            ['libelle' => 'Créances et emplois assimilés', 'brut' => null, 'amort' => null, 'net' => $creances, 'total' => false],
            ['libelle' => 'Trésorerie-actif (banques, caisses, mobile money)', 'brut' => null, 'amort' => null, 'net' => $tresoActif, 'total' => false],
        ];

        $totalActif = (int) collect($actif)->sum('net');
        $actif[] = ['libelle' => 'TOTAL ACTIF', 'brut' => null, 'amort' => null, 'net' => $totalActif, 'total' => true];

        $passif = [
            ['libelle' => 'Capitaux propres et ressources assimilées (dont résultat de l\'exercice)', 'net' => $capitaux, 'total' => false],
            ['libelle' => 'Dettes financières et ressources assimilées', 'net' => $dettesFin, 'total' => false],
            ['libelle' => 'Passif circulant (fournisseurs, personnel, État, organismes sociaux…)', 'net' => $passifCirculant, 'total' => false],
            ['libelle' => 'Trésorerie-passif (découverts, crédits de trésorerie)', 'net' => $tresoPassif, 'total' => false],
        ];

        $totalPassif = (int) collect($passif)->sum('net');
        $passif[] = ['libelle' => 'TOTAL PASSIF', 'net' => $totalPassif, 'total' => true];

        return [
            'actif' => $actif,
            'passif' => $passif,
            'total_actif' => $totalActif,
            'total_passif' => $totalPassif,
            'ecart' => $totalActif - $totalPassif,
            'equilibre' => $totalActif === $totalPassif,
        ];
    }

    /** Produits, charges et résultat de chaque section analytique (cars, gares, activités). */
    public static function analytique(int $compagnieId, int $exerciceId): array
    {
        return DB::table('cpt_lignes as l')
            ->join('cpt_ecritures as e', 'e.id', '=', 'l.ecriture_id')
            ->join('cpt_comptes as c', 'c.id', '=', 'l.compte_id')
            ->join('cpt_sections as s', 's.id', '=', 'l.section_id')
            ->where('e.compagnie_id', $compagnieId)->where('e.exercice_id', $exerciceId)->where('e.statut', 'comptabilise')
            ->whereIn('c.classe', [6, 7])
            ->groupBy('s.id', 's.code', 's.libelle', 's.type')
            ->orderBy('s.code')
            ->selectRaw('s.code, s.libelle, s.type, SUM(CASE WHEN c.classe = 7 THEN l.credit - l.debit ELSE 0 END) as produits, SUM(CASE WHEN c.classe = 6 THEN l.debit - l.credit ELSE 0 END) as charges')
            ->get()
            ->map(fn ($r) => [
                'code' => $r->code,
                'libelle' => $r->libelle,
                'type' => $r->type,
                'produits' => (int) $r->produits,
                'charges' => (int) $r->charges,
                'resultat' => (int) $r->produits - (int) $r->charges,
            ])->all();
    }

    /** Mouvements d'un compte, avec le solde cumulé. */
    public static function grandLivre(int $compagnieId, int $exerciceId, int $compteId): array
    {
        $lignes = DB::table('cpt_lignes as l')
            ->join('cpt_ecritures as e', 'e.id', '=', 'l.ecriture_id')
            ->where('l.compte_id', $compteId)
            ->where('e.compagnie_id', $compagnieId)->where('e.exercice_id', $exerciceId)->where('e.statut', 'comptabilise')
            ->orderBy('e.date_ecriture')->orderBy('e.id')->orderBy('l.id')
            ->get(['e.date_ecriture', 'e.numero', 'e.libelle as lib_e', 'l.libelle as lib_l', 'l.debit', 'l.credit']);

        $cumul = 0;
        $rows = [];

        foreach ($lignes as $l) {
            $cumul += (int) $l->debit - (int) $l->credit;
            $rows[] = [
                Carbon::parse($l->date_ecriture)->format('d/m/Y'),
                $l->numero,
                $l->lib_l ?: $l->lib_e,
                (int) $l->debit,
                (int) $l->credit,
                $cumul,
            ];
        }

        return $rows;
    }
}
