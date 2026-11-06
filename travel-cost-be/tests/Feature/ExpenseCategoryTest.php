<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ApiTestCase;

class ExpenseCategoryTest extends ApiTestCase
{
    public function test_category_in_use_cannot_be_deleted_and_expense_is_preserved(): void
    {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);
        $category = $this->categoryIn($trip);
        $expense = $this->expenseIn($trip, $owner, $category);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/trips/{$trip->id}/expense-categories/{$category->id}"
        )->assertConflict();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
        ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_unused_category_can_be_deleted(): void
    {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);
        $category = $this->categoryIn($trip);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/trips/{$trip->id}/expense-categories/{$category->id}"
        )->assertOk();

        $this->assertDatabaseMissing('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_member_cannot_delete_category(): void
    {
        $owner = $this->activeUser();
        $member = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        $this->joinTrip($trip, $member);

        $category = $this->categoryIn($trip);

        Sanctum::actingAs($member);

        $this->deleteJson(
            "/api/trips/{$trip->id}/expense-categories/{$category->id}"
        )->assertForbidden();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_cannot_be_deleted_through_another_trip_url(): void
    {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);
        $otherTrip = $this->tripOwnedBy($owner);
        $category = $this->categoryIn($trip);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/trips/{$otherTrip->id}/expense-categories/{$category->id}"
        )->assertNotFound();

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_same_category_name_is_allowed_in_different_trips(): void
    {
        $owner = $this->activeUser();
        $otherOwner = $this->activeUser();

        $trip = $this->tripOwnedBy($owner);
        $otherTrip = $this->tripOwnedBy($otherOwner);

        $this->categoryIn($trip, 'Food');

        Sanctum::actingAs($otherOwner);

        $this->postJson(
            "/api/trips/{$otherTrip->id}/expense-categories",
            ['name' => 'Food']
        )
            ->assertCreated()
            ->assertJsonPath('data.trip_id', $otherTrip->id);

        $this->assertSame(
            2,
            ExpenseCategory::where('name', 'Food')->count()
        );
    }

    public function test_duplicate_category_name_in_same_trip_is_rejected(): void
    {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        $this->categoryIn($trip, 'Food');

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/trips/{$trip->id}/expense-categories",
            ['name' => 'Food']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(
            1,
            ExpenseCategory::where('trip_id', $trip->id)->count()
        );
    }

    public function test_update_keeps_current_name_but_rejects_another_category_name_in_same_trip(): void
    {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);
        $category = $this->categoryIn($trip, 'Food');

        $this->categoryIn($trip, 'Hotel');

        Sanctum::actingAs($owner);

        $url = "/api/trips/{$trip->id}/expense-categories/{$category->id}";

        $this->patchJson($url, [
            'name' => 'Food',
        ])->assertOk();

        $this->patchJson($url, [
            'name' => 'Hotel',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('Food', $category->fresh()->name);
    }

    public function test_category_responses_do_not_expose_nonexistent_updated_at(): void
    {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        Sanctum::actingAs($owner);

        $url = "/api/trips/{$trip->id}/expense-categories";

        $created = $this->postJson($url, [
            'name' => 'Food',
        ])->assertCreated();

        $this->assertArrayNotHasKey(
            'updated_at',
            $created->json('data')
        );

        $this->assertArrayHasKey(
            'created_at',
            $created->json('data')
        );

        $id = $created->json('data.id');

        $updated = $this->patchJson("$url/$id", [
            'name' => 'Meals',
        ])->assertOk();

        $this->assertArrayNotHasKey(
            'updated_at',
            $updated->json('data')
        );

        $listed = $this->getJson($url)->assertOk();

        $this->assertArrayNotHasKey(
            'updated_at',
            $listed->json('data.0')
        );
    }
}