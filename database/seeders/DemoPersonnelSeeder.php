<?php

namespace Database\Seeders;

use App\Models\BulletinPaie;
use App\Models\Compagnie;
use App\Models\ContratEmploye;
use App\Models\Employe;
use App\Models\PeriodePaie;
use App\Models\User;
use App\Services\Personnel\AccesPersonnel;
use App\Services\Personnel\CalculPaie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoPersonnelSeeder extends Seeder
{
    private const MATRICULE = 'DEMO-RH-0001';

    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->command?->error('Refusé : les données RH fictives sont limitées aux environnements local et testing.');

            return;
        }

        $utilisateursAutorises = User::query()->where('actif', true)->get(['id']);
        $acteur = $utilisateursAutorises->first(fn (User $user) => $user->isSuperAdmin())
            ?? $utilisateursAutorises->first(fn (User $user) => AccesPersonnel::paieGestion($user));

        if (! $acteur) {
            $this->command?->error('Aucun super administrateur ou administrateur de compagnie autorisé à gérer la paie.');

            return;
        }

        $compagnies = Compagnie::query()->where('actif', true);

        if (! $acteur->isSuperAdmin()) {
            $compagnies->whereKey($acteur->compagnieId() ?? 0);
        }

        $compagnie = $compagnies->orderBy('id')->first();

        if (! $compagnie) {
            $this->command?->error('Aucune compagnie active : créez une compagnie avant les données de démonstration.');

            return;
        }

        if (Employe::query()->where('compagnie_id', $compagnie->id)->where('matricule', self::MATRICULE)->exists()) {
            $this->command?->warn('Les données RH de démonstration existent déjà. Lancez SupprimerDonneesDemoSeeder pour les retirer.');

            return;
        }

        $periodeDebut = now()->subMonthNoOverflow()->startOfMonth();

        for ($mois = 0; $mois < 12; $mois++) {
            $periodeExiste = PeriodePaie::query()
                ->where('compagnie_id', $compagnie->id)
                ->where('annee', $periodeDebut->year)
                ->where('mois', $periodeDebut->month)
                ->exists();

            if (! $periodeExiste) {
                break;
            }

            $periodeDebut->subMonthNoOverflow();
        }

        if (PeriodePaie::query()
            ->where('compagnie_id', $compagnie->id)
            ->where('annee', $periodeDebut->year)
            ->where('mois', $periodeDebut->month)
            ->exists()) {
            $this->command?->error('Aucune période libre dans les 12 derniers mois pour isoler le bulletin de démonstration.');

            return;
        }

        if ($this->command && ! $this->command->option('no-interaction') && ! $this->command->confirm(
            "Créer un employé et un bulletin fictifs dans la compagnie « {$compagnie->nom} » ?"
        )) {
            return;
        }

        $guard = auth()->guard();
        $acteurPrecedent = $guard->user();
        $guard->setUser($acteur);

        try {
            DB::transaction(function () use ($acteur, $compagnie, $periodeDebut): void {
                $employe = Employe::query()->create([
                    'compagnie_id' => $compagnie->id,
                    'matricule' => self::MATRICULE,
                    'nom' => 'DEMO',
                    'prenoms' => 'Employé Fictif',
                    'nationalite' => 'Burkinabè',
                    'situation_familiale' => 'celibataire',
                    'poste' => 'Agent de démonstration',
                    'service' => 'administration',
                    'statut' => 'actif',
                    'date_embauche' => $periodeDebut->toDateString(),
                    'mode_paiement' => 'virement',
                    'observations' => 'Données fictives — créées par DemoPersonnelSeeder.',
                    'cree_par' => $acteur->id,
                ]);

                $contrat = ContratEmploye::query()->create([
                    'employe_id' => $employe->id,
                    'type' => 'cdi',
                    'date_debut' => $periodeDebut->toDateString(),
                    'categorie' => 'Démo',
                    'mode' => 'mensuel',
                    'salaire_base' => 250000,
                    'heures_hebdo' => 40,
                    'elements_fixes' => [
                        ['libelle' => 'Indemnité de transport (démo)', 'montant' => 15000, 'soumis_cnss' => true, 'soumis_iuts' => true],
                    ],
                    'actif' => true,
                    'cree_par' => $acteur->id,
                ]);

                $periode = PeriodePaie::query()->create([
                    'compagnie_id' => $compagnie->id,
                    'annee' => $periodeDebut->year,
                    'mois' => $periodeDebut->month,
                    'statut' => 'brouillon',
                ]);

                $calcul = CalculPaie::calculer($employe, $contrat, $periode);

                BulletinPaie::query()->create($calcul + [
                    'periode_paie_id' => $periode->id,
                    'compagnie_id' => $compagnie->id,
                    'employe_id' => $employe->id,
                    'contrat_id' => $contrat->id,
                    'statut_paiement' => 'a_payer',
                ]);
            });
        } finally {
            if ($acteurPrecedent) {
                $guard->setUser($acteurPrecedent);
            } else {
                $guard->forgetUser();
            }
        }

        $this->command?->info("Employé fictif et bulletin créés pour « {$compagnie->nom} ». Aucun autre bulletin n'a été généré.");
    }
}
