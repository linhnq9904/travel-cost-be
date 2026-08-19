<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        ExpenseCategory::insert([
            [
                'name' => 'Ăn uống',
                'icon' => 'utensils',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Di chuyển',
                'icon' => 'car',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Khách sạn',
                'icon' => 'hotel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Vé tham quan',
                'icon' => 'ticket',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mua sắm',
                'icon' => 'shopping-bag',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Giải trí',
                'icon' => 'gamepad',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Khác',
                'icon' => 'ellipsis',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
