<?php

namespace Database\Factories;

use App\Models\Proprietaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proprietaire>
 */
class ProprietaireFactory extends Factory
{
    protected $model = Proprietaire::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'             => User::factory()->proprietaire(),
            'proprietaire_id'     => 'PROP-' . fake()->unique()->numberBetween(10000, 99999),
            'is_actif'             => true,
            'subscription_status'  => 'pending_plan',
            'trial_ends_at'       => null,
            'plan'                => 'starter',
            'billing_cycle'       => null,
            'subscription_ends_at' => null,
            'cancelled_at'        => null,
        ];
    }

    /**
     * Create a proprietaire with active trial.
     *
     * @return $this
     */
    public function withTrial(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'trial',
            'plan'                => 'starter',
            'billing_cycle'       => 'monthly',
            'trial_ends_at'       => now()->addDays($days),
        ]);
    }

    /**
     * Create a proprietaire with active subscription.
     *
     * @return $this
     */
    public function withActiveSubscription(string $plan = 'starter', string $cycle = 'monthly'): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status'  => 'active',
            'plan'                 => $plan,
            'billing_cycle'        => $cycle,
            'subscription_ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Create a proprietaire with expired trial.
     *
     * @return $this
     */
    public function withExpiredTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'trial',
            'plan'                => 'starter',
            'billing_cycle'       => 'monthly',
            'trial_ends_at'       => now()->subDays(1),
        ]);
    }

    /**
     * Create a proprietaire with expired subscription.
     *
     * @return $this
     */
    public function withExpiredSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status'  => 'expired',
            'plan'                 => 'starter',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->subDays(1),
        ]);
    }

    /**
     * Create a proprietaire with cancelled subscription.
     *
     * @return $this
     */
    public function withCancelledSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'cancelled',
            'plan'                 => 'starter',
            'billing_cycle'        => 'monthly',
            'subscription_ends_at' => now()->subDays(1),
            'cancelled_at'        => now()->subDays(1),
        ]);
    }
}
