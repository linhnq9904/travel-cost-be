<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class TripBalanceTest extends ApiTestCase
{
    public static function mixedCaseFields(): iterable
    {
        yield 'uppercase payer' => [true, false];
        yield 'uppercase participant' => [false, true];
        yield 'both uppercase' => [true, true];
    }

    #[DataProvider('mixedCaseFields')]
    public function test_balances_canonicalize_uuid_values_returned_by_models(
        bool $uppercasePayer,
        bool $uppercaseParticipant
    ): void {
        $owner = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        $expense = $this->expenseIn(
            $trip,
            $owner,
            $this->categoryIn($trip)
        );

        $expense->participants()->create([
            'user_id' => $owner->id,
            'share_type' => 'Equal',
            'share_amount' => '100.00',
        ]);

        Sanctum::actingAs($owner);

        // Simulate model values without changing database foreign keys.
        if ($uppercasePayer) {
            Event::listen(
                'eloquent.retrieved: ' . Expense::class,
                function (Expense $model) {
                    $model->paid_by = strtoupper($model->paid_by);
                }
            );
        }

        if ($uppercaseParticipant) {
            Event::listen(
                'eloquent.retrieved: ' . ExpenseParticipant::class,
                function (ExpenseParticipant $model) {
                    $model->user_id = strtoupper($model->user_id);
                }
            );
        }

        try {
            $this->getJson("/api/trips/{$trip->id}/balances")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.user_id', $owner->id)
                ->assertJsonPath('data.0.balance', '0.00');
        } finally {
            Event::forget('eloquent.retrieved: ' . Expense::class);
            Event::forget('eloquent.retrieved: ' . ExpenseParticipant::class);
        }
    }

    public function test_balances_preserve_cents_and_sum_to_zero(): void
    {
        $owner = $this->activeUser();
        $member = $this->activeUser();

        $trip = $this->tripOwnedBy($owner);

        $this->joinTrip($trip, $member);

        $expense = $this->expenseIn(
            $trip,
            $owner,
            $this->categoryIn($trip),
            ['amount' => '100.01']
        );

        $expense->participants()->create([
            'user_id' => $owner->id,
            'share_type' => 'Equal',
            'share_amount' => '50.01',
        ]);

        $expense->participants()->create([
            'user_id' => $member->id,
            'share_type' => 'Equal',
            'share_amount' => '50.00',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson(
            "/api/trips/{$trip->id}/balances"
        )->assertOk();

        $balances = collect($response->json('data'))
            ->pluck('balance', 'user_id')
            ->all();

        $this->assertSame('50.00', $balances[$owner->id]);
        $this->assertSame('-50.00', $balances[$member->id]);
    }
}