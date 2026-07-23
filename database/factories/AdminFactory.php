<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'user_id'   => User::factory()->admin(),
            'admin_id'  => 'ADM-' . str_pad($counter++, 5, '0', STR_PAD_LEFT),
            'username'  => fake()->unique()->userName(),
            'is_active' => true,
        ];
    }
}
