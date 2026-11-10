<?php

namespace Database\Seeders;

use App\Models\Compagnie;
use App\Models\CptCompte;
use App\Models\CptEcriture;
use App\Models\CptJournal;
use App\Models\CptLigne;
use App\Models\CptSection;
use App\Models\User;
use App\Services\Compta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoComptabiliteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->command?->error('Refusé : les écritures fictives sont limitées aux environnements local et testing.');

            return;
        }

        if (CptEcriture::query()->where('reference', 'like', 'DEMO-CPT-%')->exists()) {
            $this->command?->warn('Des écritures de démonstration existent déjà. Aucune nouvelle écriture n’a été créée.');

            return;
        }

        $acteur = User::query()->where('actif', true)->get(['id'])
            ->first(fn (User $user) => $user->isSuperAdmin());

        if (! $acteur) {
            $this->command?->error('Aucun super administrateur actif : impossible de comptabiliser les écritures fictives.');

            return;
        }

        $compagnie = Compagnie::query()->where('actif', true)->orderBy('id')->first();

        if (! $compagnie) {
            $this->command?->error('Aucune compagnie active : créez une compagnie avant les écritures de démonstration.');

            return;
        }

        $operations = [
            [
                'journal' => 'CA',
                'reference' => 'DEMO-CPT-VENTE-CAISSE',
                'libelle' => 'DEMO - ventes guichet (exemple fictif)',
                'section' => 'GUI',
                'debit' => ['571', 125000],
                'credit' => ['706', 125000],
            ],
            [
                'journal' => 'BQ',
                'reference' => 'DEMO-CPT-VENTE-BANQUE',
                'libelle' => 'DEMO - recettes billetterie banque (exemple fictif)',
                'section' => 'BIL',
                'debit' => ['521', 285000],
                'credit' => ['706', 285000],
            ],
            [
                'journal' => 'AC',
                'reference' => 'DEMO-CPT-ACHAT-CARBURANT',
                'libelle' => 'DEMO - facture carburant (exemple fictif)',
                'section' => 'ADM',
                'debit' => ['6042', 48000],
                'credit' => ['401', 48000],
            ],
            [
                'journal' => 'BQ',
                'reference' => 'DEMO-CPT-REGLEMENT-FOURNISSEUR',
                'libelle' => 'DEMO - règlement fournisseur (exemple fictif)',
                'section' => 'ADM',
                'debit' => ['401', 48000],
                'credit' => ['521', 48000],
            ],
            [
                'journal' => 'BQ',
                'reference' => 'DEMO-CPT-VERSEMENT-CAISSE',
                'libelle' => 'DEMO - versement caisse en banque (exemple fictif)',
                'section' => 'GUI',
                'debit' => ['521', 85000],
                'credit' => ['571', 85000],
            ],
        ];

        DB::transaction(function () use ($acteur, $compagnie, $operations): void {
            Compta::initialiser((int) $compagnie->id);
            $exercice = Compta::exerciceOuvert((int) $compagnie->id, today());

            if (! $exercice) {
                throw new \RuntimeException('Aucun exercice comptable ouvert à la date du jour.');
            }

            $journaux = CptJournal::query()
                ->where('compagnie_id', $compagnie->id)
                ->whereIn('code', collect($operations)->pluck('journal')->unique())
                ->pluck('id', 'code');
            $sections = CptSection::query()
                ->where('compagnie_id', $compagnie->id)
                ->whereIn('code', collect($operations)->pluck('section')->unique())
                ->pluck('id', 'code');

            $comptes = CptCompte::query()
                ->where('actif', true)
                ->whereIn('numero', collect($operations)->flatMap(fn (array $operation) => [
                    $operation['debit'][0],
                    $operation['credit'][0],
                ])->unique())
                ->where(fn ($query) => $query->whereNull('compagnie_id')->orWhere('compagnie_id', $compagnie->id))
                ->orderByRaw('compagnie_id IS NOT NULL')
                ->get()
                ->unique('numero')
                ->keyBy('numero');

            foreach ($operations as $operation) {
                $journalId = $journaux->get($operation['journal']);
                $debitCompteId = $comptes->get($operation['debit'][0])?->id;
                $creditCompteId = $comptes->get($operation['credit'][0])?->id;
                $sectionId = $sections->get($operation['section']);

                if (! $journalId || ! $debitCompteId || ! $creditCompteId || ! $sectionId) {
                    throw new \RuntimeException('Le plan comptable, le journal ou la section requis pour la démonstration est incomplet.');
                }

                $ecriture = CptEcriture::query()->create([
                    'compagnie_id' => $compagnie->id,
                    'exercice_id' => $exercice->id,
                    'journal_id' => $journalId,
                    'date_ecriture' => today(),
                    'libelle' => $operation['libelle'],
                    'reference' => $operation['reference'],
                    'cree_par' => $acteur->id,
                ]);

                CptLigne::query()->create([
                    'ecriture_id' => $ecriture->id,
                    'compte_id' => $debitCompteId,
                    'libelle' => $operation['libelle'],
                    'debit' => $operation['debit'][1],
                    'credit' => 0,
                    'section_id' => $sectionId,
                ]);

                CptLigne::query()->create([
                    'ecriture_id' => $ecriture->id,
                    'compte_id' => $creditCompteId,
                    'libelle' => $operation['libelle'],
                    'debit' => 0,
                    'credit' => $operation['credit'][1],
                    'section_id' => $sectionId,
                ]);

                $ecriture->comptabiliser($acteur);
            }
        });

        $this->command?->info("5 écritures fictives équilibrées ont été comptabilisées pour « {$compagnie->nom} ».");
    }
}
