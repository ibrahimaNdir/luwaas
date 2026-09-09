<?php

namespace Database\Factories;

use App\Models\Logement;
use App\Models\Propriete;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Logement>
 */
class LogementFactory extends Factory
{
    protected $model = Logement::class;

    public function definition(): array
    {
        return [
            'propriete_id'         => Propriete::factory(),
            'numero'               => fake()->unique()->bothify('##?'),
            'typelogement'         => fake()->randomElement(['studio', 'appartement', 'maison', 'villa', 'chambre', 'bureau', 'local_commercial', 'magasin']),
            'superficie'           => fake()->numberBetween(20, 200),
            'nombre_chambres'      => fake()->numberBetween(1, 5),
            'nombre_salles_de_bain'=> fake()->numberBetween(1, 3),
            'meuble'               => false,
            'etat'                 => 'bon',
            'description'          => fake()->sentence(),
            'prix_loyer'           => fake()->numberBetween(50000, 500000),
            'statut_occupe'        => 'disponible',
            'statut_publication'   => 'brouillon',
        ];
    }

    /**
     * Logement publié et disponible.
     */
    public function publie(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut_publication' => 'publie',
            'statut_occupe'      => 'disponible',
        ]);
    }

    /**
     * Logement occupé.
     */
    public function occupe(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut_occupe' => 'occupe',
        ]);
    }
}
