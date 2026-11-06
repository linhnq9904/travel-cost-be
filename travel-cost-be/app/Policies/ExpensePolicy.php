<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\TripMember;
use App\Models\User;

class ExpensePolicy
{
    private function canManage(User $user, Expense $expense): bool
    {
        $membership = TripMember::query()
            ->where('trip_id', $expense->trip_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return false;
        }

        if (in_array($membership->role, ['Owner', 'Admin'], true)) {
            return true;
        }

        $userId = strtolower((string) $user->id);

        return $userId === strtolower((string) $expense->created_by)
            || $userId === strtolower((string) $expense->paid_by);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->canManage($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->canManage($user, $expense);
    }

    public function updateParticipants(User $user, Expense $expense): bool
    {
        return $this->canManage($user, $expense);
    }
}