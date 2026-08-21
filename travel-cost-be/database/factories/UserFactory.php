<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => fake()->randomElement(['ACTIVE', 'INACTIVE']),
            'last_login_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-3 months', 'now') : null,
        ];
    }
}
