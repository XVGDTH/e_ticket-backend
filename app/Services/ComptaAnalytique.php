<?php

namespace App\Services;

use App\Models\CptEcriture;
use App\Models\CptImputation;
use Illuminate\Support\Facades\DB;

/**
 * Comptabilité analytique avec montants imputés.
 * Une ligne de charge/produit peut être ventilée sur plusieurs sections ;
 * sans ventilation, une ligne rattachée à une section compte pour 100 % de son montant.
 */
class ComptaAnalytique
{
    /** Remplace Compta::analytique() : même format de retour, plus le nombre de lignes imputées. */
    public static function resultats(int $compagnieId, int $exerciceId): array
    {
        $base = fn () => DB::table('cpt_lignes as l')
            ->join('cpt_ecritures as e', 'e.id', '=', 'l.ecriture_id')
            ->join('cpt_comptes as c', 'c.id', '=', 'l.compte_id')
            ->where('e.compagnie_id', $compagnieId)
            ->where('e.exercice_id', $exerciceId)
            ->where('e.statut', 'comptabilise')
            ->whereIn('c.classe', [6, 7]);

        // 1. Lignes avec une section unique et sans ventilation : 100 % du montant
        $simples = $base()
            ->join('cpt_sections as s', 's.id', '=', 'l.section_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('cpt_imputations as i')->whereColumn('i.ligne_id', 'l.id'))
            ->groupBy('s.id')
            ->selectRaw('s.id as sid, SUM(CASE WHEN c.classe = 7 THEN l.credit - l.debit ELSE 0 END) as produits, SUM(CASE WHEN c.classe = 6 THEN l.debit - l.credit ELSE 0 END) as charges, COUNT(*) as nb')
            ->get();

        // 2. Lignes ventilées : montant imputé, signé selon le sens de la ligne
        $ventilees = $base()
            ->join('cpt_imputations as i', 'i.ligne_id', '=', 'l.id')
            ->groupBy('i.section_id')
            ->selectRaw('i.section_id as sid, SUM(CASE WHEN c.classe = 7 THEN (CASE WHEN l.credit > 0 THEN i.montant ELSE -i.montant END) ELSE 0 END) as produits, SUM(CASE WHEN c.classe = 6 THEN (CASE WHEN l.debit > 0 THEN i.montant ELSE -i.montant END) ELSE 0 END) as charges, COUNT(*) as nb')
            ->get();

        $cumul = [];
        foreach ($simples->concat($ventilees) as $r) {
            $cumul[$r->sid] ??= ['produits' => 0, 'charges' => 0, 'nb' => 0];
            $cumul[$r->sid]['produits'] += (int) $r->produits;
            $cumul[$r->sid]['charges'] += (int) $r->charges;
            $cumul[$r->sid]['nb'] += (int) $r->nb;
        }

        if (! $cumul) {
            return [];
        }

        return DB::table('cpt_sections')->whereIn('id', array_keys($cumul))->orderBy('code')->get()
            ->map(fn ($s) => [
                'code' => $s->code,
                'libelle' => $s->libelle,
                'type' => $s->type,
                'produits' => $cumul[$s->id]['produits'],
                'charges' => $cumul[$s->id]['charges'],
                'resultat' => $cumul[$s->id]['produits'] - $cumul[$s->id]['charges'],
                'lignes' => $cumul[$s->id]['nb'],
            ])->all();
    }

    /**
     * Enregistre la ventilation d'une écriture. $items : [['rang' => 1, 'section_id' => 3, 'montant' => 5000], ...]
     * « rang » = numéro de la ligne dans l'écriture (1 = première).
     */
    public static function enregistrer(CptEcriture $e, array $items): void
    {
        $lignes = $e->lignes()->get()->values();

        CptImputation::whereIn('ligne_id', $lignes->pluck('id'))->delete();

        foreach ($items as $it) {
            $ligne = $lignes[(int) ($it['rang'] ?? 0) - 1] ?? null;
            $montant = (int) ($it['montant'] ?? 0);

            if (! $ligne || ! filled($it['section_id'] ?? null) || $montant <= 0) {
                continue;
            }

            CptImputation::create([
                'compagnie_id' => $e->compagnie_id,
                'ligne_id' => $ligne->id,
                'section_id' => (int) $it['section_id'],
                'montant' => $montant,
                'pourcentage' => round($montant * 100 / max($ligne->montant(), 1), 2),
            ]);
        }
    }

    /** Pré-remplissage du formulaire de modification. */
    public static function pourFormulaire(CptEcriture $e): array
    {
        $e->load('lignes.imputations');

        return $e->lignes->values()->flatMap(
            fn ($l, $i) => $l->imputations->map(fn ($im) => [
                'rang' => $i + 1, 'section_id' => $im->section_id, 'montant' => $im->montant,
            ])
        )->values()->all();
    }
}
