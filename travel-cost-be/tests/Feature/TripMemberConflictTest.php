<?php

namespace Tests\Feature;

use App\Models\TripMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ApiTestCase;

class TripMemberConflictTest extends ApiTestCase
{
    public function test_sequential_duplicate_member_returns_conflict_and_does_not_change_role(): void
    {
        $owner = $this->activeUser();
        $target = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        Sanctum::actingAs($owner);

        $url = "/api/trips/{$trip->id}/members";

        $this->postJson($url, [
            'user_id' => $target->id,
            'role' => 'Member',
        ])->assertCreated();

        $this->postJson($url, [
            'user_id' => $target->id,
            'role' => 'Admin',
        ])->assertConflict();

        $this->assertSame(
            1,
            TripMember::where('trip_id', $trip->id)
                ->where('user_id', $target->id)
                ->count()
        );

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $target->id,
            'role' => 'Member',
        ]);
    }

    public function test_unique_conflict_after_exists_check_returns_409_without_duplicate_membership(): void
    {
        $owner = $this->activeUser();
        $target = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        Sanctum::actingAs($owner);

        Event::listen(
            'eloquent.creating: ' . TripMember::class,
            function (TripMember $membership) use ($trip, $target) {
                if (
                    $membership->trip_id === $trip->id
                    && $membership->user_id === $target->id
                ) {
                    DB::table('trip_members')->insert([
                        'id' => (string) Str::uuid(),
                        'trip_id' => $trip->id,
                        'user_id' => $target->id,
                        'role' => 'Admin',
                        'joined_at' => now(),
                    ]);
                }
            }
        );

        try {
            $this->postJson("/api/trips/{$trip->id}/members", [
                'user_id' => $target->id,
                'role' => 'Member',
            ])->assertConflict();
        } finally {
            Event::forget('eloquent.creating: ' . TripMember::class);
        }

        $this->assertSame(
            1,
            TripMember::where('trip_id', $trip->id)
                ->where('user_id', $target->id)
                ->count()
        );

        $this->assertDatabaseHas('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $target->id,
            'role' => 'Admin',
        ]);
    }

    public function test_unrelated_unique_failure_is_not_misreported_as_duplicate_membership(): void
    {
        $owner = $this->activeUser();
        $target = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        $existing = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        Sanctum::actingAs($owner);

        Event::listen(
            'eloquent.creating: ' . TripMember::class,
            function (TripMember $membership) use ($existing) {
                $membership->id = $existing->id;
            }
        );

        $this->withoutExceptionHandling();

        $caught = null;

        try {
            $this->postJson("/api/trips/{$trip->id}/members", [
                'user_id' => $target->id,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $caught = $exception;
        } finally {
            Event::forget('eloquent.creating: ' . TripMember::class);
        }

        $this->assertInstanceOf(
            UniqueConstraintViolationException::class,
            $caught
        );

        $this->assertDatabaseMissing('trip_members', [
            'trip_id' => $trip->id,
            'user_id' => $target->id,
        ]);
    }
}