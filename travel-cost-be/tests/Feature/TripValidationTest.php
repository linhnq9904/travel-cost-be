<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class TripValidationTest extends ApiTestCase
{
    private User $owner;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->activeUser();
        $this->trip = $this->tripOwnedBy($this->owner);

        Sanctum::actingAs($this->owner);
    }

    public static function nullFields(): iterable
    {
        foreach ([
            'title',
            'destination',
            'start_date',
            'end_date',
            'budget',
            'currency',
            'status',
        ] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('nullFields')]
    public function test_required_fields_cannot_be_set_to_null(string $field): void
    {
        $before = $this->trip->fresh()->getAttributes();

        $this->patchJson("/api/trips/{$this->trip->id}", [
            $field => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame($before, $this->trip->fresh()->getAttributes());
    }

    public static function invalidDates(): iterable
    {
        yield 'only end before persisted start' => [
            ['end_date' => '2026-11-09'],
            'end_date',
        ];

        yield 'only start after persisted end' => [
            ['start_date' => '2026-11-16'],
            'start_date',
        ];

        yield 'both dates reversed' => [
            [
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-19',
            ],
            'end_date',
        ];

        yield 'invalid calendar date' => [
            ['end_date' => '2026-02-30'],
            'end_date',
        ];

        yield 'unexpected date format' => [
            ['start_date' => '10/11/2026'],
            'start_date',
        ];

        yield 'array date' => [
            ['start_date' => ['2026-11-10']],
            'start_date',
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_effective_date_ranges_do_not_mutate_trip(
        array $payload,
        string $field
    ): void {
        $before = $this->trip->fresh()->getAttributes();

        $this->patchJson("/api/trips/{$this->trip->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame($before, $this->trip->fresh()->getAttributes());
    }

    public function test_partial_update_preserves_dates_and_allows_nullable_description(): void
    {
        $this->patchJson("/api/trips/{$this->trip->id}", [
            'title' => 'Updated trip',
            'description' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated trip');

        $trip = $this->trip->fresh();

        $this->assertSame('2026-11-10', $trip->start_date->format('Y-m-d'));
        $this->assertSame('2026-11-15', $trip->end_date->format('Y-m-d'));
        $this->assertNull($trip->description);
    }

    public function test_valid_date_changes_and_equal_dates_are_allowed(): void
    {
        $url = "/api/trips/{$this->trip->id}";

        $this->patchJson($url, [
            'start_date' => '2026-11-11',
            'end_date' => '2026-11-16',
        ])->assertOk();

        $this->assertSame(
            '2026-11-11',
            $this->trip->fresh()->start_date->format('Y-m-d')
        );

        $this->assertSame(
            '2026-11-16',
            $this->trip->fresh()->end_date->format('Y-m-d')
        );

        $this->patchJson($url, [
            'end_date' => '2026-11-11',
        ])->assertOk();

        $this->assertTrue(
            $this->trip->fresh()->start_date->equalTo(
                $this->trip->fresh()->end_date
            )
        );
    }

    public function test_put_also_rejects_null_title(): void
    {
        $title = $this->trip->title;

        $this->putJson("/api/trips/{$this->trip->id}", [
            'title' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertSame($title, $this->trip->fresh()->title);
    }

    public static function invalidBudgets(): iterable
    {
        foreach ([
            '10000000000000.00',
            '99999999999999999999999999.00',
            '-1',
            '1.001',
            'not-money',
            null,
        ] as $value) {
            foreach (['create', 'update'] as $operation) {
                yield $operation . ' ' . json_encode($value) => [
                    $operation,
                    $value,
                ];
            }
        }
    }

    #[DataProvider('invalidBudgets')]
    public function test_invalid_budgets_are_rejected_before_database_writes(
        string $operation,
        mixed $budget
    ): void {
        $before = $this->trip->fresh()->getAttributes();
        $count = Trip::count();

        $response = $operation === 'create'
            ? $this->postJson('/api/trips', array_replace(
                $this->tripPayload(),
                ['budget' => $budget]
            ))
            : $this->patchJson("/api/trips/{$this->trip->id}", [
                'budget' => $budget,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget');

        $this->assertSame($count, Trip::count());
        $this->assertSame($before, $this->trip->fresh()->getAttributes());
    }

    public function test_budget_upper_bound_is_accepted_on_create_and_update(): void
    {
        $this->postJson('/api/trips', array_replace(
            $this->tripPayload(),
            ['budget' => '9999999999999.99']
        ))->assertCreated();

        $this->patchJson("/api/trips/{$this->trip->id}", [
            'budget' => '9999999999999.99',
        ])->assertOk();
    }

    public function test_budget_is_optional_on_create_and_preserved_when_omitted_on_update(): void
    {
        $response = $this->postJson(
            '/api/trips',
            $this->tripPayload()
        )->assertCreated();

        $this->assertSame(
            '0.00',
            Trip::findOrFail($response->json('data.id'))->budget
        );

        $this->patchJson("/api/trips/{$this->trip->id}", [
            'title' => 'Budget untouched',
        ])->assertOk();

        $this->assertSame('100.00', $this->trip->fresh()->budget);
    }

    private function tripPayload(): array
    {
        return [
            'title' => 'New trip',
            'destination' => 'Hanoi',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-15',
        ];
    }
}