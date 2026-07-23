<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * GET /auctions/{id}/live — the one-shot call a client makes on first
 * load/reconnect to get everything the WS stream only pushes deltas for
 * afterward (see AuctionsController::live() and ADR-0012 for viewer_count).
 */
test('the live snapshot includes the auction, live viewer count, and recent bids from Redis', function () {
    $auction = Auction::factory()->active()->create(['current_value' => 150]);

    Redis::sadd("auction:{$auction->id}:viewers", 1, 2, 3);
    Redis::lpush("auction:{$auction->id}:recent_bids", json_encode([
        'id' => 5, 'bidder_id' => 2, 'amount' => '150.00', 'placed_at' => '2026-07-23T02:00:00+00:00',
    ]));

    $response = $this->getJson("/api/auctions/{$auction->id}/live")->assertOk();

    $response->assertJsonPath('auction.id', $auction->id)
        ->assertJsonPath('auction.status', 'active')
        ->assertJsonPath('viewer_count', 3)
        ->assertJsonPath('recent_bids.0.id', 5)
        ->assertJsonPath('recent_bids.0.bidder_id', 2);
});

test('the live snapshot falls back to the bids table when the Redis recent-bids list is empty', function () {
    $auction = Auction::factory()->active()->create();
    $bidder = User::factory()->create();

    Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 120, 'status' => 'accepted']);

    $this->getJson("/api/auctions/{$auction->id}/live")
        ->assertOk()
        ->assertJsonPath('viewer_count', 0)
        ->assertJsonPath('recent_bids.0.bidder_id', $bidder->id);
});

test('the live snapshot for a non-existent auction returns 404', function () {
    $this->getJson('/api/auctions/999999/live')->assertNotFound();
});

test('viewing the live snapshot requires no authentication', function () {
    $auction = Auction::factory()->active()->create();

    $this->getJson("/api/auctions/{$auction->id}/live")->assertOk();
});

test('after_bid_id switches recent_bids to a gap-fill of everything missed, ignoring the Redis cache', function () {
    $auction = Auction::factory()->active()->create();
    $bidder = User::factory()->create();

    // Populated to prove ?after_bid_id bypasses it entirely, not just
    // that it happens to return the right thing when Redis is empty.
    Redis::lpush("auction:{$auction->id}:recent_bids", json_encode([
        'id' => 999, 'bidder_id' => 1, 'amount' => '999.00', 'placed_at' => now()->toAtomString(),
    ]));

    $missed1 = Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 110, 'status' => 'accepted']);
    $missed2 = Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 130, 'status' => 'accepted']);

    $response = $this->getJson("/api/auctions/{$auction->id}/live?after_bid_id={$missed1->id}")->assertOk();

    expect($response->json('recent_bids'))->toHaveCount(1);
    $response->assertJsonPath('recent_bids.0.id', $missed2->id);
});

test('after_bid_id returns nothing when there is nothing new since that bid', function () {
    $auction = Auction::factory()->active()->create();
    $bidder = User::factory()->create();
    $lastSeen = Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 110, 'status' => 'accepted']);

    $this->getJson("/api/auctions/{$auction->id}/live?after_bid_id={$lastSeen->id}")
        ->assertOk()
        ->assertJsonPath('recent_bids', []);
});
