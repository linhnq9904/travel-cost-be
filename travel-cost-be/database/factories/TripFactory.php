<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-6 months', '+3 months');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(2, 14) . ' days');

        return [
            'owner_id' => User::factory(),
            'title' => 'Chuyến đi ' . fake()->city(),
            'description' => fake()->sentence(10),
            'destination' => fake()->city(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'budget' => fake()->randomFloat(2, 2000000, 50000000),
            'currency' => 'VND',
            'status' => fake()->randomElement(['Planning', 'Ongoing', 'Finished']),
        ];
    }
}