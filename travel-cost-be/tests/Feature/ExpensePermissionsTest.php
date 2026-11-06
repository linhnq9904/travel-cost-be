<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IdempotencyKey;
use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpensePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private array $users;

    private Trip $trip;

    private Trip $otherTrip;

    private ExpenseCategory $category;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'owner',
            'admin',
            'creator',
            'payer',
            'member',
            'outsider',
            'other_admin',
        ] as $role) {
            $this->users[$role] = User::factory()->create([
                'status' => 'ACTIVE',
            ]);
        }

        $this->trip = Trip::factory()->create([
            'owner_id' => $this->users['owner']->id,
        ]);

        $this->otherTrip = Trip::factory()->create([
            'owner_id' => $this->users['outsider']->id,
        ]);

        foreach ([
            'owner' => 'Owner',
            'admin' => 'Admin',
            'creator' => 'Member',
            'payer' => 'Member',
            'member' => 'Member',
            'other_admin' => 'Member',
        ] as $name => $role) {
            $this->addMember($this->trip, $name, $role);
        }

        $this->addMember($this->otherTrip, 'outsider', 'Owner');
        $this->addMember($this->otherTrip, 'other_admin', 'Admin');

        $this->category = ExpenseCategory::create([
            'trip_id' => $this->trip->id,
            'name' => 'Test category',
        ]);
    }

    public static function mutationCases(): iterable
    {
        foreach ([
            'owner' => 200,
            'admin' => 200,
            'creator' => 200,
            'payer' => 200,
            'member' => 403,
            'outsider' => 403,
            'other_admin' => 403,
            'guest' => 401,
        ] as $role => $status) {
            foreach (['update', 'delete', 'split'] as $action) {
                yield "$role can execute $action only when authorized" => [
                    $role,
                    $action,
                    $status,
                ];
            }
        }
    }

    #[DataProvider('mutationCases')]
    public function test_mutations_enforce_role_permissions(
        string $role,
        string $action,
        int $status
    ): void {
        $expense = $this->createExpense();
        $before = $this->expenseState($expense);

        if ($role !== 'guest') {
            Sanctum::actingAs($this->users[$role]);
        }

        $this->mutate($expense, $action)->assertStatus($status);

        if ($status !== 200) {
            $this->assertSame($before, $this->expenseState($expense));
        } elseif ($action === 'delete') {
            $this->assertDatabaseMissing('expenses', [
                'id' => $expense->id,
            ]);

            $this->assertDatabaseMissing('expense_participants', [
                'expense_id' => $expense->id,
            ]);
        } elseif ($action === 'update') {
            $this->assertSame('Updated title', $expense->fresh()->title);

            $this->assertSame(
                $this->users['creator']->id,
                $expense->fresh()->created_by
            );
        } else {
            $this->assertSame(1, $expense->participants()->count());

            $this->assertSame(
                '100.00',
                $expense->participants()->firstOrFail()->share_amount
            );
        }
    }

    public static function legacyCases(): iterable
    {
        foreach ([
            'owner' => 200,
            'admin' => 200,
            'payer' => 200,
            'creator' => 403,
            'member' => 403,
        ] as $role => $status) {
            foreach (['update', 'delete', 'split'] as $action) {
                yield "$role $action with unknown creator" => [
                    $role,
                    $action,
                    $status,
                ];
            }
        }
    }

    #[DataProvider('legacyCases')]
    public function test_legacy_expenses_do_not_grant_unproven_creator_permissions(
        string $role,
        string $action,
        int $status
    ): void {
        $expense = $this->createExpense([
            'created_by' => null,
        ]);

        $before = $this->expenseState($expense);

        Sanctum::actingAs($this->users[$role]);

        $this->mutate($expense, $action)->assertStatus($status);

        if ($status === 403) {
            $this->assertSame($before, $this->expenseState($expense));
        } elseif ($action === 'delete') {
            $this->assertDatabaseMissing('expenses', [
                'id' => $expense->id,
            ]);
        } else {
            $this->assertNull($expense->fresh()->created_by);
        }
    }

    public static function revokedCases(): iterable
    {
        foreach (['creator', 'payer'] as $role) {
            foreach (['update', 'delete', 'split'] as $action) {
                yield "removed $role cannot $action" => [
                    $role,
                    $action,
                ];
            }
        }
    }

    #[DataProvider('revokedCases')]
    public function test_removed_members_lose_creator_and_payer_permissions(
        string $role,
        string $action
    ): void {
        $expense = $this->createExpense();
        $before = $this->expenseState($expense);

        TripMember::where('trip_id', $this->trip->id)
            ->where('user_id', $this->users[$role]->id)
            ->delete();

        Sanctum::actingAs($this->users[$role]);

        $this->mutate($expense, $action)->assertForbidden();

        $this->assertSame($before, $this->expenseState($expense));
    }

    public function test_creation_records_authenticated_creator_and_preserves_idempotency(): void
    {
        Sanctum::actingAs($this->users['creator']);

        $headers = [
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $payload = $this->creationPayload();
        $count = Expense::count();

        $first = $this->postJson(
            $this->baseUrl(),
            $payload,
            $headers
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.created_by',
                $this->users['creator']->id
            )
            ->assertJsonPath(
                'data.paid_by',
                $this->users['payer']->id
            );

        $id = $first->json('data.id');

        $this->postJson(
            $this->baseUrl(),
            $payload,
            $headers
        )
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath(
                'data.created_by',
                $this->users['creator']->id
            );

        $this->assertSame($count + 1, Expense::count());

        $this->postJson(
            $this->baseUrl(),
            array_replace($payload, [
                'amount' => '101.00',
            ]),
            $headers
        )->assertConflict();

        $this->assertSame('100.00', Expense::findOrFail($id)->amount);
        $this->assertSame($count + 1, Expense::count());

        $this->deleteJson($this->baseUrl() . '/' . $id)->assertOk();

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $headers['Idempotency-Key'],
            'expense_id' => null,
        ]);

        $this->postJson(
            $this->baseUrl(),
            $payload,
            $headers
        )->assertConflict();

        $this->assertDatabaseMissing('expenses', [
            'id' => $id,
        ]);
    }

    public function test_client_cannot_spoof_creator_on_creation(): void
    {
        Sanctum::actingAs($this->users['creator']);

        $expenseCount = Expense::count();
        $keyCount = IdempotencyKey::count();

        $this->postJson(
            $this->baseUrl(),
            array_replace($this->creationPayload(), [
                'created_by' => $this->users['member']->id,
            ]),
            ['Idempotency-Key' => (string) Str::uuid()]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('created_by');

        $this->assertSame($expenseCount, Expense::count());
        $this->assertSame($keyCount, IdempotencyKey::count());
    }

    public static function creatorChangeCases(): iterable
    {
        yield 'creator' => ['creator'];
        yield 'owner' => ['owner'];
    }

    #[DataProvider('creatorChangeCases')]
    public function test_even_authorized_users_cannot_change_creator(
        string $role
    ): void {
        $expense = $this->createExpense();
        $before = $this->expenseState($expense);

        Sanctum::actingAs($this->users[$role]);

        $this->patchJson($this->baseUrl() . '/' . $expense->id, [
            'created_by' => $this->users['member']->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('created_by');

        $this->assertSame($before, $this->expenseState($expense));
    }

    public function test_member_cannot_gain_access_by_submitting_their_own_payer_id(): void
    {
        $expense = $this->createExpense();
        $before = $this->expenseState($expense);

        Sanctum::actingAs($this->users['member']);

        $this->patchJson($this->baseUrl() . '/' . $expense->id, [
            'paid_by' => $this->users['member']->id,
        ])->assertForbidden();

        $this->assertSame($before, $this->expenseState($expense));
    }

    public function test_payer_transfer_changes_payer_permissions_but_preserves_creator(): void
    {
        $expense = $this->createExpense();

        Sanctum::actingAs($this->users['payer']);

        $this->patchJson($this->baseUrl() . '/' . $expense->id, [
            'paid_by' => $this->users['member']->id,
        ])->assertOk();

        $before = $this->expenseState($expense);

        $this->mutate($expense, 'update')->assertForbidden();

        $this->assertSame($before, $this->expenseState($expense));

        Sanctum::actingAs($this->users['member']);

        $this->mutate($expense, 'update')->assertOk();

        Sanctum::actingAs($this->users['creator']);

        $this->mutate($expense, 'update')->assertOk();

        $this->assertSame(
            $this->users['creator']->id,
            $expense->fresh()->created_by
        );
    }

    public static function actionCases(): iterable
    {
        foreach (['update', 'delete', 'split'] as $action) {
            yield $action => [$action];
        }
    }

    #[DataProvider('actionCases')]
    public function test_expenses_are_scoped_to_the_trip_in_the_url(
        string $action
    ): void {
        $expense = $this->createExpense();
        $before = $this->expenseState($expense);

        Sanctum::actingAs($this->users['outsider']);

        $this->mutate(
            $expense,
            $action,
            $this->otherTrip
        )->assertNotFound();

        $this->assertSame($before, $this->expenseState($expense));
    }

    public function test_ordinary_members_can_still_view_expenses(): void
    {
        $expense = $this->createExpense();

        Sanctum::actingAs($this->users['member']);

        $this->getJson($this->baseUrl() . '/' . $expense->id)
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id);
    }

    private function addMember(
        Trip $trip,
        string $name,
        string $role
    ): void {
        TripMember::create([
            'trip_id' => $trip->id,
            'user_id' => $this->users[$name]->id,
            'role' => $role,
        ]);
    }

    private function createExpense(array $overrides = []): Expense
    {
        $expense = Expense::create(array_replace(
            $this->creationPayload(),
            [
                'trip_id' => $this->trip->id,
                'created_by' => $this->users['creator']->id,
            ],
            $overrides
        ));

        foreach (['creator', 'payer'] as $role) {
            $expense->participants()->create([
                'user_id' => $this->users[$role]->id,
                'share_type' => 'Equal',
                'share_amount' => '50.00',
            ]);
        }

        return $expense;
    }

    private function creationPayload(): array
    {
        return [
            'category_id' => $this->category->id,
            'paid_by' => $this->users['payer']->id,
            'title' => 'Original title',
            'amount' => '100.00',
            'expense_date' => '2026-11-10',
        ];
    }

    private function baseUrl(?Trip $trip = null): string
    {
        return '/api/trips/' . ($trip ?? $this->trip)->id . '/expenses';
    }

    private function mutate(
        Expense $expense,
        string $action,
        ?Trip $trip = null
    ): TestResponse {
        $url = $this->baseUrl($trip) . '/' . $expense->id;

        return match ($action) {
            'update' => $this->patchJson($url, [
                'title' => 'Updated title',
            ]),
            'delete' => $this->deleteJson($url),
            'split' => $this->putJson($url . '/participants', [
                'split_type' => 'Equal',
                'participants' => [
                    [
                        'user_id' => ($trip ?? $this->trip)->owner_id,
                    ],
                ],
            ]),
        };
    }

    private function expenseState(Expense $expense): array
    {
        return [
            'expense' => $expense->fresh()->getAttributes(),
            'participants' => $expense->participants()
                ->orderBy('user_id')
                ->get()
                ->map(fn($participant) => $participant->getAttributes())
                ->all(),
        ];
    }
}