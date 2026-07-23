<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The channel upgraded from private-auction.{id} to presence-auction.{id}
 * in Fase 8 (ADR-0012) — role classification (seller/bidder/viewer) is
 * covered in PresenceChannelAuthTest; this file stays focused on the
 * ADR-0011 guard concern (Sanctum, not session).
 */
test('an authenticated user can authorize a subscription to an auction presence channel', function () {
    $user = User::factory()->create();
    $auction = Auction::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => "presence-auction.{$auction->id}",
        'socket_id' => '1234.5678',
    ])->assertOk()->assertJsonStructure(['auth', 'channel_data']);
});

test('broadcasting auth requires a Sanctum token, not a session — the ADR-0011 gotcha', function () {
    // No Sanctum::actingAs() and no session: this is exactly the failure
    // mode the default `channels:` shorthand on withRouting() produces
    // (registers /broadcasting/auth under the `web` guard, which never
    // authenticates a Bearer-token client). withBroadcasting(..., ['middleware'
    // => ['auth:sanctum']]) in bootstrap/app.php is what makes this 401
    // instead of silently misauthorizing every channel subscription.
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'presence-auction.1',
        'socket_id' => '1234.5678',
    ])->assertUnauthorized();
});
