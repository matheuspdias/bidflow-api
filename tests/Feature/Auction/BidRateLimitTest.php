<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('bid placement is rate limited after too many attempts', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    // The named limiter allows 20/minute; each attempt uses a fresh
    // Idempotency-Key so idempotency replay doesn't short-circuit the count.
    // Amounts stay below the minimum acceptable on purpose (422, not 201) so
    // current_value never changes and every attempt is evaluated identically
    // — only the rate limiter itself should eventually trip.
    for ($i = 0; $i < 20; $i++) {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 1])
            ->assertUnprocessable();
    }

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 1])
        ->assertStatus(429);
});
