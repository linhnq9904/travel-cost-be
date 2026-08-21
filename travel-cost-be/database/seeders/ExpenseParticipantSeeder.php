<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\Trip;
use Illuminate\Database\Seeder;

class ExpenseParticipantSeeder extends Seeder
{
    public function run(): void
    {
        Expense::with('trip.members')->get()->each(function (Expense $expense) {
            $memberIds = $expense->trip->members->pluck('user_id');

            if ($memberIds->isEmpty()) {
                return;
            }

            $shareAmount = round($expense->amount / $memberIds->count(), 2);

            foreach ($memberIds as $userId) {
                ExpenseParticipant::firstOrCreate(
                    ['expense_id' => $expense->id, 'user_id' => $userId],
                    [
                        'share_type' => 'Equal',
                        'share_percent' => round(100 / $memberIds->count(), 2),
                        'share_amount' => $shareAmount,
                    ]
                );
            }
        });
    }
}
