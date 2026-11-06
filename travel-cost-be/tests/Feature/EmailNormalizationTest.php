<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class EmailNormalizationTest extends ApiTestCase
{
    public function test_registration_normalizes_email_and_rejects_case_only_duplicates(): void
    {
        $payload = [
            'email' => '  Issue07@Example.Test  ',
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
            'full_name' => 'Email test',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertCreated()
            ->assertJsonPath('user.email', 'issue07@example.test');

        $this->assertDatabaseHas('users', [
            'email' => 'issue07@example.test',
        ]);

        $count = User::count();

        $this->postJson('/api/auth/register', array_replace(
            $payload,
            ['email' => 'ISSUE07@EXAMPLE.TEST']
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame($count, User::count());
    }

    public static function loginEmails(): iterable
    {
        yield 'uppercase' => ['ISSUE07@EXAMPLE.TEST'];
        yield 'whitespace' => ['  Issue07@example.test  '];
    }

    #[DataProvider('loginEmails')]
    public function test_login_accepts_email_case_and_whitespace_variants(
        string $email
    ): void {
        $user = $this->activeUser([
            'email' => 'issue07@example.test',
            'password' => Hash::make('TestPass123!'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'TestPass123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function test_normalized_email_does_not_bypass_password_verification(): void
    {
        $user = $this->activeUser([
            'email' => 'issue07@example.test',
            'password' => Hash::make('TestPass123!'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'ISSUE07@EXAMPLE.TEST',
            'password' => 'WrongPass123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, $user->tokens()->count());
    }

    public static function updateMethods(): iterable
    {
        yield 'current account PUT' => ['put'];
        yield 'own account PATCH' => ['patch'];
    }

    #[DataProvider('updateMethods')]
    public function test_account_updates_normalize_email_and_allow_keeping_current_email(
        string $method
    ): void {
        $user = $this->activeUser([
            'email' => 'original@example.test',
        ]);

        Sanctum::actingAs($user);

        $url = $method === 'put'
            ? '/api/user'
            : "/api/user/{$user->id}";

        $this->json(strtoupper($method), $url, [
            'email' => '  Updated07@Example.Test  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'updated07@example.test');

        $this->json(strtoupper($method), $url, [
            'email' => 'UPDATED07@EXAMPLE.TEST',
        ])->assertOk();

        $this->assertSame(
            'updated07@example.test',
            $user->fresh()->email
        );
    }

    public function test_account_cannot_take_another_accounts_email_using_case_variants(): void
    {
        $other = $this->activeUser([
            'email' => 'taken@example.test',
        ]);

        $user = $this->activeUser([
            'email' => 'original@example.test',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/user', [
            'email' => ' TAKEN@EXAMPLE.TEST ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('original@example.test', $user->fresh()->email);
        $this->assertSame('taken@example.test', $other->fresh()->email);
    }

    public function test_password_only_update_preserves_email(): void
    {
        $user = $this->activeUser([
            'email' => 'unchanged@example.test',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/user', [
            'password' => 'ChangedPass123!',
            'password_confirmation' => 'ChangedPass123!',
        ])->assertOk();

        $this->assertSame('unchanged@example.test', $user->fresh()->email);

        $this->assertTrue(
            Hash::check('ChangedPass123!', $user->fresh()->password)
        );
    }

    public static function malformedEmailEndpoints(): iterable
    {
        yield 'registration' => ['register'];
        yield 'login' => ['login'];
        yield 'account update' => ['update'];
    }

    #[DataProvider('malformedEmailEndpoints')]
    public function test_array_email_is_a_validation_error_not_a_server_error(
        string $endpoint
    ): void {
        $payload = [
            'email' => ['invalid'],
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
            'full_name' => 'Email test',
        ];

        $count = User::count();

        if ($endpoint === 'update') {
            $user = $this->activeUser();
            $count++;

            Sanctum::actingAs($user);

            $response = $this->putJson('/api/user', [
                'email' => ['invalid'],
            ]);
        } else {
            $response = $this->postJson(
                '/api/auth/' . $endpoint,
                $payload
            );
        }

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame($count, User::count());
    }
}