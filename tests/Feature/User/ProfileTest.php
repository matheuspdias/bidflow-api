<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('an authenticated user can view their profile', function () {
    $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Alice')
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonPath('data.is_blocked', false);
});

test('an authenticated user can update their name and email', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson('/api/profile', ['name' => 'Alice Updated', 'email' => 'alice-updated@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Alice Updated')
        ->assertJsonPath('data.email', 'alice-updated@example.com');

    $this->assertDatabaseHas('users', ['email' => 'alice-updated@example.com']);
});

test('updating the profile fails when the email belongs to another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'alice@example.com']);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson('/api/profile', ['name' => 'Alice', 'email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('profile endpoints require authentication', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
