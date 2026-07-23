<?php

namespace Database\Factories;

use App\Models\Demande;
use App\Models\Locataire;
use App\Models\Logement;
use App\Models\Proprietaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Demande>
 */
class DemandeFactory extends Factory
{
    protected $model = Demande::class;

    public function definition(): array
    {
        return [
            'logement_id'     => Logement::factory(),
            'locataire_id'    => Locataire::factory(),
            'proprietaire_id' => Proprietaire::factory()->withActiveSubscription(),
            'status'          => 'en_attente',
            'date_demande'    => now(),
            'rappel_envoye'   => false,
        ];
    }

    public function acceptee(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'          => 'acceptee',
            'date_acceptation'=> now(),
        ]);
    }

    public function refusee(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refusee',
        ]);
    }

    public function annulee(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'annulee',
        ]);
    }
}
