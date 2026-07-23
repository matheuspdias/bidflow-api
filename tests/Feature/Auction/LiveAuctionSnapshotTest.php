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
