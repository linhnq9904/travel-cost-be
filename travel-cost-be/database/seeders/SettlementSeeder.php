<?php

namespace Database\Seeders;

use App\Models\Settlement;
use App\Models\Trip;
use Illuminate\Database\Seeder;

class SettlementSeeder extends Seeder
{
    public function run(): void
    {
        Trip::with('members')->get()->each(function (Trip $trip) {
            $memberIds = $trip->members->pluck('user_id');

            if ($memberIds->count() < 2) {
                return;
            }

            $from = $memberIds->random();
            $to = $memberIds->where('id', '!=', $from)->random() ?? $memberIds->first();

            Settlement::create([
                'trip_id' => $trip->id,
                'from_user' => $from,
                'to_user' => $to,
                'amount' => fake()->randomFloat(2, 50000, 2000000),
                'status' => fake()->randomElement(['Pending', 'Paid']),
                'paid_at' => fake()->boolean(50) ? now() : null,
            ]);
        });
    }
}
