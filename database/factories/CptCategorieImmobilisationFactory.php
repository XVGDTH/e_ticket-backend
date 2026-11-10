<?php

namespace Database\Factories;

use App\Models\Compagnie;
use App\Models\CptCategorieImmobilisation;
use App\Models\CptCompte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CptCategorieImmobilisation>
 */
class CptCategorieImmobilisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compagnie_id' => Compagnie::factory(),
            'code' => fake()->unique()->bothify('CAT-#####'),
            'libelle' => 'Matériel de transport',
            'duree_utilite_mois' => 60,
            'mode_amortissement' => 'lineaire',
            'premiere_periode' => 'prorata_temporis',
            'compte_actif_id' => CptCompte::factory()->state(['numero' => '2451', 'classe' => 2]),
            'compte_amortissement_id' => CptCompte::factory()->state(['numero' => '2845', 'classe' => 2]),
            'compte_charge_id' => CptCompte::factory()->state(['numero' => '681', 'classe' => 6]),
            'actif' => true,
            'cree_par' => null,
        ];
    }
}
