<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;

test('a user can log in with the correct credentials and receives a token', function () {
    User::factory()->create([
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'alice@example.com')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
});

test('login fails with the wrong password', function () {
    User::factory()->create([
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'alice@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized()
        ->assertJson(['message' => 'Invalid credentials.']);
});

test('login fails for an email that does not exist', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized();
});

test('login fails for a blocked user even with the correct password', function () {
    User::factory()->blocked()->create([
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);

    $response->assertForbidden()
        ->assertJson(['message' => 'This user is blocked.']);
});
