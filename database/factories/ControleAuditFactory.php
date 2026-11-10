<?php

namespace Database\Factories;

use App\Models\Compagnie;
use App\Models\ControleAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControleAudit>
 */
class ControleAuditFactory extends Factory
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
            'titre' => fake()->sentence(4),
            'objectif' => fake()->paragraph(),
            'procedure' => fake()->paragraph(),
            'frequence' => 'mensuelle',
            'niveau_risque' => 'moyen',
            'prochaine_echeance' => today()->addMonth(),
            'actif' => true,
        ];
    }
}
