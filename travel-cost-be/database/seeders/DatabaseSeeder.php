<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ExpenseCategorySeeder::class,
            // UserSeeder::class,
            ProfileSeeder::class,
            TripSeeder::class,
            // ExpenseSeeder::class,
        ]);
    }
}
