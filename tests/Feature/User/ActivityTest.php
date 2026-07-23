<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('activity endpoints return empty collections for a user with no history', function (string $endpoint) {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson($endpoint)->assertOk()->assertJson(['data' => []]);
})->with([
    '/api/profile/bids',
    '/api/profile/auctions/won',
    '/api/profile/auctions/lost',
]);

test('bid history lists the authenticated user own bids across auctions, most recent first', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $auction = Auction::factory()->active()->create(['name' => 'Vintage Camera']);

    // created_at isn't fillable on Bid (the bids.created_at column
    // defaults to CURRENT_TIMESTAMP — see EloquentBidRepository), so
    // ordering here relies on id as the tiebreaker (see
    // BidHistoryLookupAdapter), not on backdating timestamps.
    Bid::create(['auction_id' => $auction->id, 'bidder_id' => $user->id, 'amount' => 110, 'status' => 'accepted']);
    Bid::create(['auction_id' => $auction->id, 'bidder_id' => $user->id, 'amount' => 130, 'status' => 'accepted']);
    Bid::create(['auction_id' => $auction->id, 'bidder_id' => $stranger->id, 'amount' => 120, 'status' => 'accepted']);

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/profile/bids')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount', '130.00')
        ->assertJsonPath('data.0.auction_name', 'Vintage Camera')
        ->assertJsonPath('data.1.amount', '110.00');
});

test('auctions won lists closed auctions where the user is the winner', function () {
    $winner = User::factory()->create();
    Auction::factory()->create(['status' => 'closed', 'winner_id' => $winner->id, 'name' => 'Won Item']);
    Auction::factory()->create(['status' => 'closed', 'winner_id' => null]);
    Auction::factory()->active()->create(['winner_id' => null]);

    Sanctum::actingAs($winner, ['*']);

    $this->getJson('/api/profile/auctions/won')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Won Item');
});

test('auctions lost lists closed auctions the user bid on but did not win', function () {
    $bidder = User::factory()->create();
    $winner = User::factory()->create();

    $lostToSomeoneElse = Auction::factory()->create(['status' => 'closed', 'winner_id' => $winner->id, 'name' => 'Lost to rival']);
    Bid::create(['auction_id' => $lostToSomeoneElse->id, 'bidder_id' => $bidder->id, 'amount' => 100, 'status' => 'accepted']);

    $reserveNotMet = Auction::factory()->create(['status' => 'closed', 'winner_id' => null, 'name' => 'Reserve not met']);
    Bid::create(['auction_id' => $reserveNotMet->id, 'bidder_id' => $bidder->id, 'amount' => 100, 'status' => 'accepted']);

    $wonThisOne = Auction::factory()->create(['status' => 'closed', 'winner_id' => $bidder->id, 'name' => 'Actually won']);
    Bid::create(['auction_id' => $wonThisOne->id, 'bidder_id' => $bidder->id, 'amount' => 100, 'status' => 'accepted']);

    $neverBidOn = Auction::factory()->create(['status' => 'closed', 'winner_id' => $winner->id]);

    Sanctum::actingAs($bidder, ['*']);

    $response = $this->getJson('/api/profile/auctions/lost')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Lost to rival', 'Reserve not met')
        ->and($names)->not->toContain('Actually won')
        ->and($response->json('meta.total'))->toBe(2);

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($neverBidOn->id);
});

test('rankings lists the top buyers by number of auctions won, enriched with their name', function () {
    $topBuyer = User::factory()->create(['name' => 'Alice']);
    $secondBuyer = User::factory()->create(['name' => 'Bob']);

    Auction::factory()->count(3)->create(['status' => 'closed', 'winner_id' => $topBuyer->id]);
    Auction::factory()->count(1)->create(['status' => 'closed', 'winner_id' => $secondBuyer->id]);

    Sanctum::actingAs($topBuyer, ['*']);

    $this->getJson('/api/rankings')
        ->assertOk()
        ->assertJsonPath('data.0.user_id', $topBuyer->id)
        ->assertJsonPath('data.0.name', 'Alice')
        ->assertJsonPath('data.0.wins', 3)
        ->assertJsonPath('data.1.user_id', $secondBuyer->id)
        ->assertJsonPath('data.1.wins', 1);
});

test('activity endpoints require authentication', function (string $endpoint) {
    $this->getJson($endpoint)->assertUnauthorized();
})->with([
    '/api/profile/bids',
    '/api/profile/auctions/won',
    '/api/profile/auctions/lost',
    '/api/rankings',
]);
