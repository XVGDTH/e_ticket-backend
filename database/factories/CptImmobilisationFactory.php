<?php

namespace Database\Factories;

use App\Models\CptCategorieImmobilisation;
use App\Models\CptImmobilisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CptImmobilisation>
 */
class CptImmobilisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categorie_id' => CptCategorieImmobilisation::factory(),
            'compagnie_id' => fn (array $attributes) => $this->categorie($attributes)->compagnie_id,
            'code' => fake()->unique()->bothify('IMM-#####'),
            'designation' => 'Autocar de démonstration',
            'description' => null,
            'date_acquisition' => '2026-01-15',
            'date_mise_en_service' => '2026-01-15',
            'cout_acquisition' => 1200000,
            'valeur_residuelle' => 120000,
            'duree_utilite_mois' => fn (array $attributes) => $this->categorie($attributes)->duree_utilite_mois,
            'mode_amortissement' => fn (array $attributes) => $this->categorie($attributes)->mode_amortissement,
            'premiere_periode' => fn (array $attributes) => $this->categorie($attributes)->premiere_periode,
            'compte_actif_id' => fn (array $attributes) => $this->categorie($attributes)->compte_actif_id,
            'compte_amortissement_id' => fn (array $attributes) => $this->categorie($attributes)->compte_amortissement_id,
            'compte_charge_id' => fn (array $attributes) => $this->categorie($attributes)->compte_charge_id,
            'statut' => 'brouillon',
            'cree_par' => null,
            'valide_par' => null,
            'validee_le' => null,
        ];
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function categorie(array $attributes): CptCategorieImmobilisation
    {
        return CptCategorieImmobilisation::query()->findOrFail($attributes['categorie_id']);
    }
}
