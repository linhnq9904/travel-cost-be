<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Trip;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Ăn uống', 'icon' => 'food', 'color' => '#FF5733'],
            ['name' => 'Di chuyển', 'icon' => 'transport', 'color' => '#33A1FF'],
            ['name' => 'Lưu trú', 'icon' => 'hotel', 'color' => '#33FF57'],
            ['name' => 'Vé tham quan', 'icon' => 'ticket', 'color' => '#FFC300'],
            ['name' => 'Mua sắm', 'icon' => 'shopping', 'color' => '#C70039'],
            ['name' => 'Khác', 'icon' => 'other', 'color' => '#900C3F'],
        ];
        $trips = Trip::all();

        foreach ($trips as $trip) {
            foreach ($categories as $category) {
                ExpenseCategory::firstOrCreate(
                    [
                        'trip_id' => $trip->id,
                        'name' => $category['name']
                    ],
                    $category
                );

            }
        }



    }
}
