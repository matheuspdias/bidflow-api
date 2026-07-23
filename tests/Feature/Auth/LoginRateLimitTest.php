<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;

test('login is rate limited after too many attempts', function () {
    User::factory()->create([
        'email' => 'alice@example.com',
        'password' => 'password123',
    ]);

    $payload = ['email' => 'alice@example.com', 'password' => 'wrong-password'];

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/login', $payload)->assertStatus(429);
});
