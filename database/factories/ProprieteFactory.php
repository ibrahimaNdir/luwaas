<?php

namespace Database\Factories;

use App\Models\Propriete;
use App\Models\Proprietaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Propriete>
 */
class ProprieteFactory extends Factory
{
    protected $model = Propriete::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proprietaire_id' => Proprietaire::factory(),
            'titre'           => fake()->words(2, true),
            'type'            => fake()->randomElement(['maison', 'villa', 'immeuble', 'appartement', 'local_commercial', 'bureau', 'magasin']),
            'adresse'         => fake()->address(),
            'description'     => fake()->paragraph(),
            'latitude'        => fake()->latitude(-14.8, -14.6),
            'longitude'       => fake()->longitude(-17.5, -17.3),
            'region_id'       => fake()->numberBetween(1, 14),
            'departement_id'  => fake()->numberBetween(1, 45),
            'commune_id'      => fake()->numberBetween(1, 100),
        ];
    }
}
