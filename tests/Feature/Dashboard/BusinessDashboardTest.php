<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;

test('the business dashboard reports auction counts by status, total bids, revenue, and live viewers', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    Auction::factory()->create(['status' => 'scheduled']);
    $activeOne = Auction::factory()->active()->create();
    $activeTwo = Auction::factory()->active()->create();
    $wonAuction = Auction::factory()->create(['status' => 'closed', 'winner_id' => $user->id, 'current_value' => 150]);
    $reserveNotMet = Auction::factory()->create(['status' => 'closed', 'winner_id' => null, 'current_value' => 90]);
    Auction::factory()->create(['status' => 'cancelled']);

    Bid::create(['auction_id' => $wonAuction->id, 'bidder_id' => $user->id, 'amount' => 150, 'status' => 'accepted']);
    Bid::create(['auction_id' => $reserveNotMet->id, 'bidder_id' => $user->id, 'amount' => 90, 'status' => 'accepted']);

    Redis::sadd("auction:{$activeOne->id}:viewers", 1, 2);
    Redis::sadd("auction:{$activeTwo->id}:viewers", 3);

    $response = $this->getJson('/api/dashboard/business')->assertOk();

    $response->assertJsonPath('data.auctions.scheduled', 1)
        ->assertJsonPath('data.auctions.active', 2)
        ->assertJsonPath('data.auctions.closed', 2)
        ->assertJsonPath('data.auctions.cancelled', 1)
        ->assertJsonPath('data.total_bids', 2)
        ->assertJsonPath('data.total_revenue', '150.00')
        ->assertJsonPath('data.live_viewers_total', 3);
});

test('total_revenue is a properly formatted zero when there are no completed sales yet', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/dashboard/business')
        ->assertOk()
        ->assertJsonPath('data.total_revenue', '0.00');
});

test('the business dashboard requires the dashboard:read ability', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['profile:read']);

    $this->getJson('/api/dashboard/business')->assertForbidden();
});

test('the business dashboard requires authentication', function () {
    $this->getJson('/api/dashboard/business')->assertUnauthorized();
});
