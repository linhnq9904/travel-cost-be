<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            AppNotification::factory()->count(3)->create([
                'user_id' => $user->id,
            ]);
        });
    }
}