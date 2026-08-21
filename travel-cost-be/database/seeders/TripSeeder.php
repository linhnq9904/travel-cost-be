<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $users->each(function (User $user) {
            Trip::factory()->count(2)->create([
                'owner_id' => $user->id,
            ]);
        });
    }
}
