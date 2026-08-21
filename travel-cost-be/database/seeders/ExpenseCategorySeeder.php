<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
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

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
