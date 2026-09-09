<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'slug' => $this->faker->unique()->word,
            'tier' => $this->faker->randomElement(['free', 'pro']),
            'billing_cycle' => $this->faker->randomElement([null, 'monthly', 'yearly']),
            'price_xof' => $this->faker->randomElement([0, 5000, 10000]),
            'publications_max' => $this->faker->randomElement([1, 10, null]),
            'features' => $this->faker->randomElement([[], ['Quittances PDF', 'Rappels SMS'], ['Quittances PDF', 'Rappels SMS', 'Export Excel']]),
            'is_active' => true,
        ];
    }
}