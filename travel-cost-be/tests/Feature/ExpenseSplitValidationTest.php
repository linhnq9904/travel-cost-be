<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class ExpenseSplitValidationTest extends ApiTestCase
{
    private User $owner;

    private User $member;

    private Expense $expense;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->activeUser();
        $this->member = $this->activeUser();

        $trip = $this->tripOwnedBy($this->owner);

        $this->joinTrip($trip, $this->member);

        $this->expense = $this->expenseIn(
            $trip,
            $this->owner,
            $this->categoryIn($trip)
        );

        $this->expense->participants()->create([
            'user_id' => $this->owner->id,
            'share_type' => 'Equal',
            'share_amount' => '100.00',
        ]);

        $this->url = "/api/trips/{$trip->id}/expenses/{$this->expense->id}/participants";

        Sanctum::actingAs($this->owner);
    }

    public function test_percentage_is_rejected_at_validation_and_preserves_existing_split(): void
    {
        $before = $this->participants();

        $this->putJson($this->url, [
            'split_type' => 'Percentage',
            'participants' => [
                [
                    'user_id' => $this->owner->id,
                    'share_percent' => 100,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('split_type');

        $this->assertSame($before, $this->participants());
    }

    public function test_equal_split_and_amount_update_preserve_every_cent(): void
    {
        $this->putJson($this->url, [
            'split_type' => 'Equal',
            'participants' => [
                ['user_id' => $this->owner->id],
                ['user_id' => $this->member->id],
            ],
        ])->assertOk();

        $this->patchJson(
            str_replace('/participants', '', $this->url),
            ['amount' => '100.01']
        )->assertOk();

        $shares = $this->expense
            ->participants()
            ->pluck('share_amount')
            ->all();

        sort($shares);

        $this->assertSame(['50.00', '50.01'], $shares);
        $this->assertSame('100.01', $this->expense->fresh()->amount);
    }

    public function test_fixed_split_requires_matching_total_and_blocks_amount_changes_atomically(): void
    {
        $payload = [
            'split_type' => 'Fixed',
            'participants' => [
                [
                    'user_id' => $this->owner->id,
                    'share_amount' => '40.00',
                ],
                [
                    'user_id' => $this->member->id,
                    'share_amount' => '60.00',
                ],
            ],
        ];

        $this->putJson($this->url, $payload)->assertOk();

        $before = $this->participants();

        $this->putJson($this->url, [
            'split_type' => 'Fixed',
            'participants' => [
                [
                    'user_id' => $this->owner->id,
                    'share_amount' => '50.00',
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');

        $this->assertSame($before, $this->participants());

        $this->patchJson(
            str_replace('/participants', '', $this->url),
            [
                'title' => 'Must roll back',
                'amount' => '200.00',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame('100.00', $this->expense->fresh()->amount);
        $this->assertSame(
            'Original expense',
            $this->expense->fresh()->title
        );

        $this->assertSame($before, $this->participants());
    }

    public function test_empty_duplicate_and_unrelated_participants_are_rejected(): void
    {
        $stranger = $this->activeUser();
        $before = $this->participants();

        $cases = [
            [],
            [
                ['user_id' => $this->owner->id],
                ['user_id' => strtoupper($this->owner->id)],
            ],
            [
                ['user_id' => $stranger->id],
            ],
        ];

        foreach ($cases as $participants) {
            $this->putJson($this->url, [
                'split_type' => 'Equal',
                'participants' => $participants,
            ])->assertUnprocessable();

            $this->assertSame($before, $this->participants());
        }
    }

    public static function invalidAmounts(): iterable
    {
        foreach ([
            '10000000000.00',
            '999999999999999999999999999.00',
            '0',
            '-1',
            '1.001',
        ] as $amount) {
            yield $amount => [$amount];
        }
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_expense_update_amount_cannot_corrupt_existing_split(
        string $amount
    ): void {
        $before = $this->participants();

        $this->patchJson(
            str_replace('/participants', '', $this->url),
            ['amount' => $amount]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame('100.00', $this->expense->fresh()->amount);
        $this->assertSame($before, $this->participants());
    }

    public function test_oversized_fixed_share_is_rejected_by_field_validation(): void
    {
        $before = $this->participants();

        $this->putJson($this->url, [
            'split_type' => 'Fixed',
            'participants' => [
                [
                    'user_id' => $this->owner->id,
                    'share_amount' => '10000000000.00',
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants.0.share_amount');

        $this->assertSame($before, $this->participants());
    }

    public function test_payer_uuid_is_normalized_on_update_without_allowing_nonmembers(): void
    {
        $url = str_replace('/participants', '', $this->url);

        $this->patchJson($url, [
            'paid_by' => ' ' . strtoupper($this->member->id) . ' ',
        ])
            ->assertOk()
            ->assertJsonPath('data.paid_by', $this->member->id);

        $stranger = $this->activeUser();

        $this->patchJson($url, [
            'paid_by' => $stranger->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paid_by');

        $this->patchJson($url, [
            'paid_by' => [$this->member->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paid_by');

        $this->patchJson($url, [
            'title' => 'Payer unchanged',
        ])->assertOk();

        $this->assertSame(
            $this->member->id,
            $this->expense->fresh()->paid_by
        );
    }

    private function participants(): array
    {
        return $this->expense
            ->participants()
            ->orderBy('user_id')
            ->get()
            ->map(fn($participant) => $participant->getAttributes())
            ->all();
    }
}