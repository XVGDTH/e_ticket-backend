<?php

namespace Database\Factories;

use App\Models\Compagnie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compagnie>
 */
class CompagnieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(2),
            'actif' => true,
        ];
    }
}
