<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('replaying the same Idempotency-Key returns the cached response instead of placing a second bid', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    $key = 'a-fixed-idempotency-key';

    $first = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 110]);

    $first->assertCreated();
    $firstBidId = $first->json('data.id');

    $second = $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 110]);

    $second->assertCreated()->assertJsonPath('data.id', $firstBidId);

    $this->assertDatabaseCount('bids', 1);
});

test('a different Idempotency-Key is treated as a brand-new request', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    $this->withHeader('Idempotency-Key', 'key-one')
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 110])
        ->assertCreated();

    $this->withHeader('Idempotency-Key', 'key-two')
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 130])
        ->assertCreated();

    $this->assertDatabaseCount('bids', 2);
});

test('the same Idempotency-Key from a different bidder is treated independently', function () {
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);

    $key = 'shared-literal-key';

    $bidderOne = User::factory()->create();
    Sanctum::actingAs($bidderOne, ['*']);
    $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 110])
        ->assertCreated();

    $bidderTwo = User::factory()->create();
    Sanctum::actingAs($bidderTwo, ['*']);
    $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 130])
        ->assertCreated();

    $this->assertDatabaseCount('bids', 2);
});
