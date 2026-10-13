<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Trip;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {

        Trip::with('members')->get()->each(function (Trip $trip) {
            $categories = ExpenseCategory::where('trip_id', $trip->id)->get();
            $memberIds = $trip->members->pluck('user_id');

            if ($memberIds->isEmpty() || $categories->isEmpty()) {
                return;
            }

            Expense::factory()->count(3)->create([
                'trip_id' => $trip->id,
                'category_id' => $categories->random()->id,
                'paid_by' => $memberIds->random(),
            ]);
        });
    }
}
