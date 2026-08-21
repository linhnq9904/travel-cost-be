<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => fake()->name(),
                    'avatar' => null,
                    'phone' => fake()->unique()->phoneNumber(),
                    'date_of_birth' => fake()->date('Y-m-d', '2005-01-01'),
                    'gender' => fake()->randomElement(['MALE', 'FEMALE', 'OTHER']),
                    'address' => fake()->address(),
                ]
            );
        });
    }
}
