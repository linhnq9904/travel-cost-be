<?php

namespace Tests\Support;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Profile;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
        ]);
    }

    protected function activeUser(array $attributes = []): User
    {
        $user = User::factory()->create(
            array_replace(['status' => 'ACTIVE'], $attributes)
        );

        Profile::create([
            'user_id' => $user->id,
            'full_name' => 'Test user',
        ]);

        return $user;
    }

    protected function tripOwnedBy(User $owner): Trip
    {
        $trip = Trip::factory()->create([
            'owner_id' => $owner->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-15',
            'budget' => '100.00',
        ]);

        $this->joinTrip($trip, $owner, 'Owner');

        return $trip;
    }

    protected function joinTrip(
        Trip $trip,
        User $user,
        string $role = 'Member'
    ): TripMember {
        return TripMember::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    protected function categoryIn(
        Trip $trip,
        string $name = 'Food'
    ): ExpenseCategory {
        return ExpenseCategory::create([
            'trip_id' => $trip->id,
            'name' => $name,
        ]);
    }

    protected function expenseIn(
        Trip $trip,
        User $payer,
        ExpenseCategory $category,
        array $attributes = []
    ): Expense {
        return Expense::create(array_replace([
            'trip_id' => $trip->id,
            'category_id' => $category->id,
            'paid_by' => $payer->id,
            'created_by' => $payer->id,
            'title' => 'Original expense',
            'amount' => '100.00',
            'expense_date' => '2026-11-10',
        ], $attributes));
    }
}