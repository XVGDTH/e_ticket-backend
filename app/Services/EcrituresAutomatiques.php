<?php

namespace App\Services;

use App\Models\CptCompte;
use App\Models\CptEcriture;
use App\Models\CptImputation;
use App\Models\CptJournal;
use App\Models\CptLigne;
use App\Models\CptSection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Point d'entrée unique pour toutes les écritures générées par le système
 * (vente de billets, colis, dotations, paiements mobile money…).
 * Chaque écriture est tracée jusqu'à sa pièce source (RG-CPT-01) et reste à valider ligne par ligne.
 *
 * Exemple :
 *   EcrituresAutomatiques::creer(
 *       compagnieId: 3, journalCode: 'VE', date: now(), libelle: 'Vente billet BK-00123',
 *       lignes: [
 *           ['compte' => '571', 'debit' => 5000, 'libelle' => 'Encaissement guichet'],
 *           ['compte' => '706', 'credit' => 5000, 'libelle' => 'Billetterie', 'imputations' => [['section' => 'BIL', 'montant' => 5000]]],
 *       ],
 *       source: $billet, estFacture: true, tiers: 'Client guichet',
 *   );
 */
class EcrituresAutomatiques
{
    public static function creer(
        int $compagnieId,
        string $journalCode,
        Carbon $date,
        string $libelle,
        array $lignes,
        ?object $source = null,
        bool $estFacture = false,
        ?string $tiers = null,
        ?string $reference = null,
    ): CptEcriture {
        Compta::initialiser($compagnieId);

        return DB::transaction(function () use ($compagnieId, $journalCode, $date, $libelle, $lignes, $source, $estFacture, $tiers, $reference) {
            $exercice = Compta::exerciceOuvert($compagnieId, $date)
                ?? throw new \DomainException('Aucun exercice ouvert pour le '.$date->format('d/m/Y').'.');

            $journal = CptJournal::where('compagnie_id', $compagnieId)->where('code', $journalCode)->firstOrFail();

            $debit = (int) collect($lignes)->sum(fn ($l) => (int) ($l['debit'] ?? 0));
            $credit = (int) collect($lignes)->sum(fn ($l) => (int) ($l['credit'] ?? 0));

            if (count($lignes) < 2 || $debit === 0 || $debit !== $credit) {
                throw new \DomainException('Écriture automatique déséquilibrée : débit '.$debit.', crédit '.$credit.'.');
            }

            $e = CptEcriture::create([
                'compagnie_id' => $compagnieId,
                'exercice_id' => $exercice->id,
                'journal_id' => $journal->id,
                'date_ecriture' => $date,
                'libelle' => mb_substr($libelle, 0, 160),
                'reference' => $reference,
                'origine' => 'automatique',
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'est_facture' => $estFacture,
                'tiers' => $tiers,
                'cree_par' => null, // générée par le système : n'importe quel validateur autorisé peut la valider
            ]);

            foreach ($lignes as $l) {
                $compte = CptCompte::where('numero', (string) $l['compte'])
                    ->where(fn ($q) => $q->whereNull('compagnie_id')->orWhere('compagnie_id', $compagnieId))
                    ->orderByDesc('compagnie_id')->firstOrFail();

                $ligne = CptLigne::create([
                    'ecriture_id' => $e->id,
                    'compte_id' => $compte->id,
                    'libelle' => $l['libelle'] ?? null,
                    'debit' => (int) ($l['debit'] ?? 0),
                    'credit' => (int) ($l['credit'] ?? 0),
                ]);

                foreach ($l['imputations'] ?? [] as $im) {
                    $section = CptSection::where('compagnie_id', $compagnieId)->where('code', $im['section'])->firstOrFail();

                    CptImputation::create([
                        'compagnie_id' => $compagnieId,
                        'ligne_id' => $ligne->id,
                        'section_id' => $section->id,
                        'montant' => (int) $im['montant'],
                        'pourcentage' => round((int) $im['montant'] * 100 / max($ligne->montant(), 1), 2),
                    ]);
                }
            }

            if ($estFacture) {
                NumerotationFactures::attribuer($e);
            }
                 $e->comptabiliser(); // les écritures automatiques sont comptabilisées directement

            return $e->refresh();
        });
    }
}
