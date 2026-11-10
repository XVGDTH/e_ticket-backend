<?php

namespace Database\Factories;

use App\Models\Compagnie;
use App\Models\RecommandationAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommandationAudit>
 */
class RecommandationAuditFactory extends Factory
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
            'constat' => fake()->paragraph(),
            'action_requise' => fake()->paragraph(),
            'priorite' => 'normale',
            'statut' => 'ouverte',
            'echeance' => today()->addMonth(),
        ];
    }
}
