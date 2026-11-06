<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IdempotencyKey;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PDOException;
use Tests\Support\ApiTestCase;

class ExpenseIdempotencyTest extends ApiTestCase
{
    private User $owner;

    private Trip $trip;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->activeUser();
        $this->trip = $this->tripOwnedBy($this->owner);
        $this->category = $this->categoryIn($this->trip);

        Sanctum::actingAs($this->owner);
    }

    public function test_replay_returns_same_expense_and_conflicts_do_not_create_duplicates(): void
    {
        $headers = [
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $first = $this->postJson(
            $this->url(),
            $this->payload(),
            $headers
        )->assertCreated();

        $id = $first->json('data.id');

        $this->postJson(
            $this->url(),
            $this->payload(),
            $headers
        )
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->postJson(
            $this->url(),
            array_replace($this->payload(), [
                'amount' => '101.00',
            ]),
            $headers
        )->assertConflict();

        $this->assertSame(1, Expense::count());
        $this->assertSame(1, IdempotencyKey::count());
        $this->assertSame('100.00', Expense::findOrFail($id)->amount);
    }

    public function test_replay_after_deletion_conflicts_without_recreating_expense(): void
    {
        $headers = [
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $id = $this->postJson(
            $this->url(),
            $this->payload(),
            $headers
        )
            ->assertCreated()
            ->json('data.id');

        $this->deleteJson($this->url() . '/' . $id)->assertOk();

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $headers['Idempotency-Key'],
            'expense_id' => null,
        ]);

        $this->postJson(
            $this->url(),
            $this->payload(),
            $headers
        )->assertConflict();

        $this->assertSame(0, Expense::count());
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_unique_failure_after_key_insert_is_rethrown_without_replay_lookup_and_rolls_back(): void
    {
        $key = (string) Str::uuid();

        $failure = new UniqueConstraintViolationException(
            'sqlite',
            'insert into expenses',
            [],
            new PDOException('Injected expense unique conflict')
        );

        $replayQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$replayQueries) {
            if (
                str_starts_with(strtolower($query->sql), 'select')
                && str_contains($query->sql, 'idempotency_keys')
            ) {
                $replayQueries[] = $query->sql;
            }
        });

        Event::listen(
            'eloquent.creating: ' . Expense::class,
            function () use ($failure) {
                throw $failure;
            }
        );

        $this->withoutExceptionHandling();

        $caught = null;

        try {
            $this->postJson(
                $this->url(),
                $this->payload(),
                ['Idempotency-Key' => $key]
            );
        } catch (UniqueConstraintViolationException $exception) {
            $caught = $exception;
        } finally {
            Event::forget('eloquent.creating: ' . Expense::class);
        }

        $this->assertSame($failure, $caught);

        $this->assertSame(
            [],
            $replayQueries,
            'A post-insert unique failure must not enter duplicate-key replay handling.'
        );

        $this->assertDatabaseMissing('idempotency_keys', [
            'key' => $key,
        ]);

        $this->assertSame(0, Expense::count());
    }

    public function test_key_is_scoped_to_authenticated_user_and_trip(): void
    {
        $headers = [
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $this->postJson(
            $this->url(),
            $this->payload(),
            $headers
        )->assertCreated();

        $member = $this->activeUser();

        $this->joinTrip($this->trip, $member);

        Sanctum::actingAs($member);

        $this->postJson(
            $this->url(),
            $this->payload(),
            $headers
        )->assertCreated();

        Sanctum::actingAs($this->owner);

        $otherTrip = $this->tripOwnedBy($this->owner);
        $otherCategory = $this->categoryIn($otherTrip);

        $this->postJson(
            "/api/trips/{$otherTrip->id}/expenses",
            array_replace($this->payload(), [
                'category_id' => $otherCategory->id,
            ]),
            $headers
        )->assertCreated();

        $this->assertSame(3, Expense::count());
        $this->assertSame(3, IdempotencyKey::count());
    }

    private function url(): string
    {
        return "/api/trips/{$this->trip->id}/expenses";
    }

    private function payload(): array
    {
        return [
            'title' => 'Meal',
            'category_id' => $this->category->id,
            'paid_by' => $this->owner->id,
            'amount' => '100.00',
            'expense_date' => '2026-11-10',
        ];
    }
}