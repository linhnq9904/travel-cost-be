<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // User cố định để test
        User::firstOrCreate(
            ['email' => 'test@gmail.com'],
            [
                'password' => Hash::make('password'),
                'status' => 'ACTIVE',
            ]
        );

        // Sinh thêm 9 user ngẫu nhiên
        User::factory()->count(9)->create();
    }
}
