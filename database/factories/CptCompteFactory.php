<?php

namespace Database\Factories;

use App\Models\CptCompte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CptCompte>
 */
class CptCompteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compagnie_id' => null,
            'numero' => '2451',
            'intitule' => 'Compte de matériel de transport',
            'classe' => 2,
            'actif' => true,
        ];
    }
}
