<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class TripMemberSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        Trip::all()->each(function (Trip $trip) use ($users) {
            // Owner luôn là member với role Owner
            TripMember::firstOrCreate(
                ['trip_id' => $trip->id, 'user_id' => $trip->owner_id],
                ['role' => 'Owner', 'joined_at' => now()]
            );

            // Thêm 2-4 thành viên ngẫu nhiên khác
            $members = $users->where('id', '!=', $trip->owner_id)
                ->random(min(3, $users->count() - 1));

            foreach ($members as $member) {
                TripMember::firstOrCreate(
                    ['trip_id' => $trip->id, 'user_id' => $member->id],
                    [
                        'role' => fake()->randomElement(['Admin', 'Member']),
                        'joined_at' => now(),
                    ]
                );
            }
        });
    }
}
