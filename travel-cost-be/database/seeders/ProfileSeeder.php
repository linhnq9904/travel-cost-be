<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $user->name ?? 'User',
                    'avatar' => null,
                    'phone' => null,
                    'date_of_birth' => null,
                ]
            );
        }
    }
}
