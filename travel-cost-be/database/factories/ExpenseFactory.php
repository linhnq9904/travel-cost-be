<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            // Lưu ý: chạy ExpenseCategorySeeder trước khi seed Expense
            'category_id' => ExpenseCategory::inRandomOrder()->value('id'),
            'paid_by' => User::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(8),
            'amount' => fake()->randomFloat(2, 20000, 3000000),
            'expense_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'receipt_image' => fake()->boolean(40)
                ? 'receipts/' . fake()->uuid() . '.jpg'
                : null,
        ];
    }
}
