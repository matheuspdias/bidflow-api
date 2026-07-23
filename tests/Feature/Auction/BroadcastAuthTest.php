<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('an authenticated user can authorize a subscription to an auction private channel', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-auction.1',
        'socket_id' => '1234.5678',
    ])->assertOk()->assertJsonStructure(['auth']);
});

test('broadcasting auth requires a Sanctum token, not a session — the ADR-0011 gotcha', function () {
    // No Sanctum::actingAs() and no session: this is exactly the failure
    // mode the default `channels:` shorthand on withRouting() produces
    // (registers /broadcasting/auth under the `web` guard, which never
    // authenticates a Bearer-token client). withBroadcasting(..., ['middleware'
    // => ['auth:sanctum']]) in bootstrap/app.php is what makes this 401
    // instead of silently misauthorizing every channel subscription.
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-auction.1',
        'socket_id' => '1234.5678',
    ])->assertUnauthorized();
});
