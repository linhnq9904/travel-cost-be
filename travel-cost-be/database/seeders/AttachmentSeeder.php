<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Expense;
use Illuminate\Database\Seeder;

class AttachmentSeeder extends Seeder
{
    public function run(): void
    {
        Expense::all()->each(function (Expense $expense) {
            if (fake()->boolean(60)) {
                Attachment::create([
                    'expense_id' => $expense->id,
                    'file_path' => 'attachments/' . fake()->uuid() . '.jpg',
                    'file_type' => 'image/jpeg',
                    'uploaded_at' => now(),
                ]);
            }
        });
    }
}
