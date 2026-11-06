<?php

namespace Tests\Feature;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ApiTestCase;

class ProfileValidationTest extends ApiTestCase
{
    public function test_phone_must_be_unique_but_current_owner_can_keep_it(): void
    {
        $owner = $this->activeUser();
        $other = $this->activeUser();

        $other->profile->update([
            'phone' => '0987654321',
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/profile', [
            'phone' => '0912345678',
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '0912345678');

        $this->patchJson('/api/profile', [
            'phone' => '0912345678',
        ])->assertOk();

        Sanctum::actingAs($other);

        $this->patchJson('/api/profile', [
            'phone' => '0912345678',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame(
            '0912345678',
            $owner->profile()->firstOrFail()->phone
        );

        $this->assertSame(
            '0987654321',
            $other->profile()->firstOrFail()->phone
        );
    }

    public function test_phone_can_be_cleared_and_unrelated_updates_preserve_it(): void
    {
        $user = $this->activeUser();

        $user->profile->update([
            'phone' => '0912345678',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'full_name' => 'Updated name',
        ])->assertOk();

        $this->assertSame(
            '0912345678',
            $user->profile()->firstOrFail()->phone
        );

        $this->patchJson('/api/profile', [
            'phone' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', null);

        $this->assertNull($user->profile()->firstOrFail()->phone);
    }

    public static function invalidPhones(): iterable
    {
        foreach ([
            'abc',
            '123',
            '091234567890',
            ['0912345678'],
            912345678,
        ] as $phone) {
            yield json_encode($phone) => [$phone];
        }
    }

    #[DataProvider('invalidPhones')]
    public function test_invalid_phone_is_rejected_without_overwriting_profile(
        mixed $phone
    ): void {
        $user = $this->activeUser();

        $user->profile->update([
            'phone' => '0912345678',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'phone' => $phone,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame(
            '0912345678',
            $user->profile()->firstOrFail()->phone
        );
    }

    public static function validGenders(): iterable
    {
        yield 'lowercase' => ['male', 'MALE'];
        yield 'uppercase' => ['FEMALE', 'FEMALE'];
        yield 'mixed case' => ['Other', 'OTHER'];
        yield 'whitespace' => [' male ', 'MALE'];
        yield 'nullable' => [null, null];
    }

    #[DataProvider('validGenders')]
    public function test_gender_is_normalized_before_validation(
        mixed $gender,
        ?string $expected
    ): void {
        $user = $this->activeUser();

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'gender' => $gender,
        ])
            ->assertOk()
            ->assertJsonPath('data.gender', $expected);

        $this->assertSame(
            $expected,
            $user->profile()->firstOrFail()->gender
        );
    }

    public static function invalidGenders(): iterable
    {
        yield 'unknown' => ['invalid'];
        yield 'number' => [123];
        yield 'array' => [['MALE']];
    }

    #[DataProvider('invalidGenders')]
    public function test_invalid_gender_preserves_existing_value(
        mixed $gender
    ): void {
        $user = $this->activeUser();

        $user->profile->update([
            'gender' => 'MALE',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'gender' => $gender,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');

        $this->assertSame(
            'MALE',
            $user->profile()->firstOrFail()->gender
        );

        $this->patchJson('/api/profile', [
            'full_name' => 'Changed name',
        ])->assertOk();

        $this->assertSame(
            'MALE',
            $user->profile()->firstOrFail()->gender
        );
    }
}