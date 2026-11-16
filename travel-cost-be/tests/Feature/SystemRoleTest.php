<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class SystemRoleTest extends ApiTestCase
{
    public function test_registration_assigns_user_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'newuser@example.test',
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
            'full_name' => 'New user',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'USER');

        $this->assertDatabaseHas('users', [
            'id' => $response->json('user.id'),
            'role' => 'USER',
        ]);
    }

    public function test_registration_cannot_assign_admin_role(): void
    {
        $count = User::count();

        $this->postJson('/api/auth/register', [
            'email' => 'fakeadmin@example.test',
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
            'full_name' => 'Fake admin',
            'role' => 'ADMIN',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame($count, User::count());
    }

    public static function roleUpdates(): iterable
    {
        foreach (['PUT', 'PATCH'] as $method) {
            foreach (['USER', 'ADMIN'] as $role) {
                yield "$method $role" => [$method, $role];
            }
        }
    }

    #[DataProvider('roleUpdates')]
    public function test_account_api_cannot_change_system_role(
        string $method,
        string $originalRole
    ): void {
        $user = $this->activeUser();

        $user->forceFill([
            'role' => $originalRole,
        ])->save();

        Sanctum::actingAs($user);

        $url = $method === 'PUT'
            ? '/api/user'
            : "/api/user/{$user->id}";

        $this->json($method, $url, [
            'role' => $originalRole === 'USER' ? 'ADMIN' : 'USER',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(
            $originalRole,
            $user->fresh()->role
        );
    }

    public function test_system_admin_can_access_admin_api(): void
    {
        $user = $this->activeUser();

        $user->forceFill([
            'role' => 'ADMIN',
        ])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'ADMIN');
    }

    public function test_trip_owner_is_not_automatically_system_admin(): void
    {
        $owner = $this->activeUser();

        $this->tripOwnedBy($owner);

        Sanctum::actingAs($owner);

        $this->getJson('/api/admin/me')
            ->assertForbidden();

        $this->assertSame('USER', $owner->fresh()->role);
    }

    public function test_guest_cannot_access_admin_api(): void
    {
        $this->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public static function inactiveStatuses(): iterable
    {
        yield 'inactive' => ['INACTIVE'];
        yield 'banned' => ['BANNED'];
    }

    #[DataProvider('inactiveStatuses')]
    public function test_inactive_admin_is_denied(string $status): void
    {
        $user = $this->activeUser([
            'status' => $status,
        ]);

        $user->forceFill([
            'role' => 'ADMIN',
        ])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/me')
            ->assertForbidden();
    }
}