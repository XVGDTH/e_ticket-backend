<?php

namespace Database\Seeders;

use App\Models\Compagnie;
use App\Models\DepenseVehicule;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoMaintenanceSeeder extends Seeder
{
    private const PREFIXE = 'DEMO-MNT-';

    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->command?->error('Refusé : les données de maintenance fictives sont limitées aux environnements local et testing.');

            return;
        }

        $acteur = User::query()->where('actif', true)->get(['id'])
            ->first(fn (User $user): bool => $user->isSuperAdmin());

        if (! $acteur) {
            $this->command?->error('Aucun super administrateur actif : impossible de créer les données de maintenance fictives.');

            return;
        }

        $compagnie = Compagnie::query()->where('actif', true)->orderBy('id')->first();

        if (! $compagnie) {
            $this->command?->error('Aucune compagnie active : créez une compagnie avant les données de démonstration.');

            return;
        }

        DB::transaction(function () use ($acteur, $compagnie): void {
            $vehiculeEnMaintenance = Vehicule::query()->firstOrCreate(
                ['compagnie_id' => $compagnie->id, 'immatriculation' => self::PREFIXE.'001'],
                [
                    'numero_parc' => 'DEMO-001',
                    'marque' => 'Toyota',
                    'modele' => 'Coaster',
                    'annee' => 2018,
                    'places' => 30,
                    'carburant' => 'gasoil',
                    'km_actuel' => 185000,
                    'statut' => 'maintenance',
                    'km_prochaine_vidange' => 185200,
                    'assurance_expire_le' => today()->addMonths(5)->toDateString(),
                    'visite_technique_expire_le' => today()->addMonths(3)->toDateString(),
                    'note' => 'Véhicule fictif de démonstration.',
                ],
            );
            $vehiculeActif = Vehicule::query()->firstOrCreate(
                ['compagnie_id' => $compagnie->id, 'immatriculation' => self::PREFIXE.'002'],
                [
                    'numero_parc' => 'DEMO-002',
                    'marque' => 'Mercedes-Benz',
                    'modele' => 'Sprinter',
                    'annee' => 2020,
                    'places' => 19,
                    'carburant' => 'gasoil',
                    'km_actuel' => 92400,
                    'statut' => 'actif',
                    'km_prochaine_vidange' => 92900,
                    'assurance_expire_le' => today()->addMonths(6)->toDateString(),
                    'visite_technique_expire_le' => today()->addMonths(4)->toDateString(),
                    'note' => 'Véhicule fictif de démonstration.',
                ],
            );

            foreach ([
                [
                    'reference' => self::PREFIXE.'CARBURANT-001',
                    'vehicule_id' => $vehiculeActif->id,
                    'date_depense' => today()->subDays(2)->toDateString(),
                    'categorie' => 'carburant',
                    'montant' => 42000,
                    'litres' => 58.5,
                    'kilometrage' => 92300,
                    'fournisseur' => 'Station Démo',
                    'note' => 'Plein fictif pour les essais.',
                ],
                [
                    'reference' => self::PREFIXE.'ENTRETIEN-001',
                    'vehicule_id' => $vehiculeEnMaintenance->id,
                    'date_depense' => today()->subDays(4)->toDateString(),
                    'categorie' => 'entretien',
                    'montant' => 68500,
                    'litres' => null,
                    'kilometrage' => 185000,
                    'fournisseur' => 'Garage Démo',
                    'note' => 'Vidange et filtres — données fictives.',
                ],
                [
                    'reference' => self::PREFIXE.'REPARATION-001',
                    'vehicule_id' => $vehiculeEnMaintenance->id,
                    'date_depense' => today()->subDays(1)->toDateString(),
                    'categorie' => 'reparation',
                    'montant' => 95000,
                    'litres' => null,
                    'kilometrage' => 185000,
                    'fournisseur' => 'Atelier Démo',
                    'note' => 'Réparation fictive pour visualiser le suivi.',
                ],
            ] as $depense) {
                DepenseVehicule::query()->firstOrCreate(
                    ['compagnie_id' => $compagnie->id, 'reference' => $depense['reference']],
                    $depense + ['cree_par' => $acteur->id],
                );
            }
        });

        $this->command?->info("Deux cars et trois dépenses fictifs sont disponibles pour « {$compagnie->nom} ».");
    }
}
