<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ApiTestCase;

class UserVisibilityTest extends ApiTestCase
{
    public function test_user_can_view_self_without_any_trip(): void
    {
        $user = $this->activeUser();

        Sanctum::actingAs($user);

        $this->getJson("/api/user/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_shared_trip_members_can_view_each_other(): void
    {
        $owner = $this->activeUser();
        $member = $this->activeUser();
        $trip = $this->tripOwnedBy($owner);

        $this->joinTrip($trip, $member);

        Sanctum::actingAs($owner);

        $this->getJson("/api/user/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $member->id);

        Sanctum::actingAs($member);

        $this->getJson("/api/user/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id);
    }

    public function test_unrelated_users_cannot_see_private_account_fields(): void
    {
        $viewer = $this->activeUser();
        $stranger = $this->activeUser();

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/api/user/{$stranger->id}")
            ->assertNotFound()
            ->assertJsonMissing([
                'email' => $stranger->email,
            ]);

        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_unknown_user_is_not_found(): void
    {
        Sanctum::actingAs($this->activeUser());

        $this->getJson('/api/user/' . Str::uuid())
            ->assertNotFound();
    }

    public function test_guest_cannot_view_user_details(): void
    {
        $user = $this->activeUser();

        $this->getJson("/api/user/{$user->id}")
            ->assertUnauthorized();
    }

    public function test_access_requires_at_least_one_remaining_shared_trip(): void
    {
        $viewer = $this->activeUser();
        $member = $this->activeUser();

        $first = $this->tripOwnedBy($viewer);
        $second = $this->tripOwnedBy($viewer);

        $firstMembership = $this->joinTrip($first, $member);
        $secondMembership = $this->joinTrip($second, $member);

        Sanctum::actingAs($viewer);

        $firstMembership->delete();

        $this->getJson("/api/user/{$member->id}")
            ->assertOk();

        $secondMembership->delete();

        $this->getJson("/api/user/{$member->id}")
            ->assertNotFound()
            ->assertJsonMissing([
                'email' => $member->email,
            ]);
    }
}