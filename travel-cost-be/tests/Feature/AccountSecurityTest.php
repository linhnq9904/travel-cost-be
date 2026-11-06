<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class AccountSecurityTest extends ApiTestCase
{
    public static function protectedUpdates(): iterable
    {
        foreach (['PUT', 'PATCH'] as $method) {
            foreach (['email', 'password'] as $field) {
                foreach (['missing', 'wrong'] as $currentPassword) {
                    yield "$method $field $currentPassword" => [
                        $method,
                        $field,
                        $currentPassword,
                    ];
                }
            }
        }
    }

    #[DataProvider('protectedUpdates')]
    public function test_sensitive_updates_require_correct_current_password(
        string $method,
        string $field,
        string $currentPassword
    ): void {
        $user = $this->account();
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $before = $user->fresh()->getAttributes();

        $payload = $field === 'email'
            ? ['email' => 'changed@example.test']
            : [
                'password' => 'NewPass123!',
                'password_confirmation' => 'NewPass123!',
            ];

        if ($currentPassword === 'wrong') {
            $payload['current_password'] = 'WrongPass123!';
        }

        $this->send(
            $method,
            $this->endpoint($method, $user),
            $payload,
            $current->plainTextToken
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertSame($before, $user->fresh()->getAttributes());

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $current->accessToken->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $other->accessToken->id,
        ]);
    }

    public static function updateMethods(): iterable
    {
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
    }

    #[DataProvider('updateMethods')]
    public function test_password_change_revokes_other_tokens_but_keeps_current(
        string $method
    ): void {
        $user = $this->account();
        $unrelatedUser = $this->account();

        $current = $user->createToken('current');
        $other = $user->createToken('other');
        $unrelated = $unrelatedUser->createToken('unrelated');

        $this->send(
            $method,
            $this->endpoint($method, $user),
            [
                'current_password' => 'OldPass123!',
                'password' => 'NewPass123!',
                'password_confirmation' => 'NewPass123!',
            ],
            $current->plainTextToken
        )->assertOk();

        $this->assertTrue(
            Hash::check('NewPass123!', $user->fresh()->password)
        );

        $this->assertFalse(
            Hash::check('OldPass123!', $user->fresh()->password)
        );

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $current->accessToken->id,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $other->accessToken->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $unrelated->accessToken->id,
        ]);

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $current->plainTextToken
        )->assertOk();

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $other->plainTextToken
        )->assertUnauthorized();

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $unrelated->plainTextToken
        )->assertOk();
    }

    #[DataProvider('updateMethods')]
    public function test_email_change_requires_password_and_preserves_password(
        string $method
    ): void {
        $user = $this->account();
        $token = $user->createToken('current');
        $originalHash = $user->password;

        $this->send(
            $method,
            $this->endpoint($method, $user),
            [
                'current_password' => 'OldPass123!',
                'email' => ' Changed@Example.Test ',
            ],
            $token->plainTextToken
        )
            ->assertOk()
            ->assertJsonPath('data.email', 'changed@example.test');

        $this->assertSame(
            'changed@example.test',
            $user->fresh()->email
        );

        $this->assertSame(
            $originalHash,
            $user->fresh()->password
        );

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $token->plainTextToken
        )->assertOk();
    }

    #[DataProvider('updateMethods')]
    public function test_invalid_new_password_does_not_change_account_or_revoke_tokens(
        string $method
    ): void {
        $user = $this->account();
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $originalHash = $user->password;

        $this->send(
            $method,
            $this->endpoint($method, $user),
            [
                'current_password' => 'OldPass123!',
                'password' => 'short',
                'password_confirmation' => 'different',
            ],
            $current->plainTextToken
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertSame(2, $user->tokens()->count());

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $other->plainTextToken
        )->assertOk();
    }

    public function test_correct_password_does_not_allow_updating_another_user(): void
    {
        $user = $this->account();
        $target = $this->account();
        $token = $user->createToken('current');

        $before = $target->fresh()->getAttributes();

        $this->send(
            'PATCH',
            "/api/user/{$target->id}",
            [
                'current_password' => 'OldPass123!',
                'email' => 'takeover@example.test',
            ],
            $token->plainTextToken
        )->assertForbidden();

        $this->assertSame(
            $before,
            $target->fresh()->getAttributes()
        );
    }

    public function test_token_older_than_configured_lifetime_is_rejected(): void
    {
        $minutes = config('sanctum.expiration');

        $this->assertIsInt($minutes);
        $this->assertGreaterThan(0, $minutes);

        $user = $this->account();
        $recent = $user->createToken('recent');
        $expired = $user->createToken('expired');

        $expired->accessToken->forceFill([
            'created_at' => now()->subMinutes($minutes + 1),
        ])->save();

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $recent->plainTextToken
        )->assertOk();

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $expired->plainTextToken
        )->assertUnauthorized();
    }

    public function test_explicitly_expired_token_is_rejected(): void
    {
        $user = $this->account();

        $token = $user->createToken(
            'expired',
            ['*'],
            now()->subMinute()
        );

        $this->send(
            'GET',
            '/api/auth/me',
            [],
            $token->plainTextToken
        )->assertUnauthorized();
    }

    private function account(): User
    {
        return $this->activeUser([
            'password' => Hash::make('OldPass123!'),
        ]);
    }

    private function endpoint(string $method, User $user): string
    {
        return $method === 'PUT'
            ? '/api/user'
            : "/api/user/{$user->id}";
    }

    private function send(
        string $method,
        string $url,
        array $payload,
        string $token
    ): TestResponse {
        // Mỗi request phải xác thực lại token từ database.
        $this->app['auth']->forgetGuards();

        return $this->json($method, $url, $payload, [
            'Authorization' => 'Bearer ' . $token,
        ]);
    }
}