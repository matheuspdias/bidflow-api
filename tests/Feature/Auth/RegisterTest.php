<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;

test('a visitor can register and receives a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'alice@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
});

test('registration fails when the email is already taken', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration fails when passwords do not match', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'something-else',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
});
