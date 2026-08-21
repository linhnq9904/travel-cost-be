<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProfileSeeder::class,
            ExpenseCategorySeeder::class,
            TripSeeder::class,
            TripMemberSeeder::class,
            ExpenseSeeder::class,
            ExpenseParticipantSeeder::class,
            SettlementSeeder::class,
            AttachmentSeeder::class,
            NotificationSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
