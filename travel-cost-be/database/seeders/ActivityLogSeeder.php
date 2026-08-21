<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Trip;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            'Tạo chuyến đi',
            'Thêm thành viên',
            'Thêm khoản chi',
            'Cập nhật ngân sách',
            'Thanh toán công nợ',
        ];

        Trip::all()->each(function (Trip $trip) use ($actions) {
            ActivityLog::create([
                'trip_id' => $trip->id,
                'user_id' => $trip->owner_id,
                'action' => fake()->randomElement($actions),
                'metadata' => ['ip' => fake()->ipv4()],
            ]);
        });
    }
}
