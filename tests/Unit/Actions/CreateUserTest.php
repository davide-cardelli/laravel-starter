<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(TestCase::class, RefreshDatabase::class);

test('create user action creates user successfully', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $action = new CreateUser;

    $userData = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+39 333 1234567',
        'email' => 'john@example.com',
        'password' => 'password123',
    ];

    $user = $action->execute($userData);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->first_name)->toBe('John');
    expect($user->last_name)->toBe('Doe');
    expect($user->phone)->toBe('+39 333 1234567');
    expect($user->email)->toBe('john@example.com');
    expect(Hash::check('password123', $user->password))->toBeTrue();

    assertDatabaseHas('users', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);
});

test('create user action hashes password', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $action = new CreateUser;

    $userData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
        'email' => 'test@example.com',
        'password' => 'plain-password',
    ];

    $user = $action->execute($userData);

    // Password should not be stored in plain text
    expect($user->password)->not->toBe('plain-password');

    // Password should be hashed and verifiable
    expect(Hash::check('plain-password', $user->password))->toBeTrue();
});

test('create user action logs operation', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $action = new CreateUser;

    $userData = [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '+39 333 1234567',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ];

    $created = $action->execute($userData);

    // The audit trail must carry identifiers only — no email/name/phone PII.
    Log::shouldHaveReceived('info')
        ->with('Creating new user', [
            'roles' => null,
            'created_by' => $admin->id,
        ])
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) use ($created) {
            return $message === 'User created successfully' &&
                   $context['user_id'] === $created->id &&
                   ! array_key_exists('email', $context);
        })
        ->once();
});

test('create user action returns user instance', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $action = new CreateUser;

    $userData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
        'email' => 'test@example.com',
        'password' => 'password123',
    ];

    $result = $action->execute($userData);

    expect($result)->toBeInstanceOf(User::class);
    expect($result->exists)->toBeTrue();
    expect($result->id)->not->toBeNull();
});
