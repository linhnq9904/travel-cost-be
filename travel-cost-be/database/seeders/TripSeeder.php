<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            Trip::create([
                'id' => (string) Str::uuid(),
                'owner_id' => $user->id,
                'title' => 'Chuyến đi Đà Nẵng',
                'description' => 'Chuyến du lịch Đà Nẵng',
                'destination' => 'Đà Nẵng',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-05',
                'budget' => 10000000,
                'currency' => 'VND',
                'status' => 'planning',
            ]);
        }
    }
}
