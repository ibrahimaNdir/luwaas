<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prenom'            => fake()->firstName(),
            'nom'               => fake()->lastName(),
            'email'             => fake()->unique()->safeEmail(),
            'telephone'         => '77' . fake()->numberBetween(1000000, 9999999),
            'password'          => Hash::make('password'),
            'is_active'         => true,
            'user_type'         => fake()->randomElement(['proprietaire', 'locataire']),
            'phone_verified_at' => now(),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's phone is not verified.
     *
     * @return $this
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Create a proprietaire user.
     *
     * @return $this
     */
    public function proprietaire(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'proprietaire',
        ]);
    }

    /**
     * Create a locataire user.
     *
     * @return $this
     */
    public function locataire(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'locataire',
        ]);
    }

    /**
     * Create an admin user.
     *
     * @return $this
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'admin',
        ]);
    }
}
