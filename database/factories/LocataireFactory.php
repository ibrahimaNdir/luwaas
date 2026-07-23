<?php

namespace Database\Factories;

use App\Models\Locataire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Locataire>
 */
class LocataireFactory extends Factory
{
    protected $model = Locataire::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory()->locataire(),
            'locataire_id' => null, // auto-généré par le modèle via booted()
            'is_actif'     => true,
        ];
    }
}
