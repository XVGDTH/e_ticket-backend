<?php

namespace Database\Seeders;

use App\Models\Compagnie;
use App\Models\CptCategorieImmobilisation;
use Illuminate\Database\Seeder;

class CptImmobilisationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modeles = [
            ['code' => 'TRANSPORT', 'libelle' => 'Matériel de transport'],
            ['code' => 'EQUIPEMENTS', 'libelle' => 'Équipements et outillage'],
            ['code' => 'MOBILIER', 'libelle' => 'Mobilier et matériel de bureau'],
        ];

        Compagnie::query()->select('id')->orderBy('id')->eachById(function (Compagnie $compagnie) use ($modeles): void {
            foreach ($modeles as $modele) {
                CptCategorieImmobilisation::query()->firstOrCreate(
                    ['compagnie_id' => $compagnie->id, 'code' => $modele['code']],
                    [
                        'libelle' => $modele['libelle'],
                        'duree_utilite_mois' => null,
                        'mode_amortissement' => 'lineaire',
                        'premiere_periode' => 'prorata_temporis',
                        'compte_actif_id' => null,
                        'compte_amortissement_id' => null,
                        'compte_charge_id' => null,
                        'actif' => false,
                    ]
                );
            }
        });
    }
}
