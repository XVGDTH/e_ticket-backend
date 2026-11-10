<?php

namespace Database\Factories;

use App\Models\CptAmortissement;
use App\Models\CptImmobilisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CptAmortissement>
 */
class CptAmortissementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'immobilisation_id' => CptImmobilisation::factory(),
            'compagnie_id' => fn (array $attributes) => CptImmobilisation::query()
                ->whereKey($attributes['immobilisation_id'])
                ->value('compagnie_id'),
            'periode_debut' => '2026-01-01',
            'periode_fin' => '2026-01-31',
            'montant' => 18000,
            'statut' => 'planifie',
            'ecriture_id' => null,
            'cree_par' => null,
            'valide_par' => null,
            'validee_le' => null,
        ];
    }
}
