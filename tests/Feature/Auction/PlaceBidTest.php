<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function placeBid(Auction $auction, array $payload, ?string $idempotencyKey = null): \Illuminate\Testing\TestResponse
{
    return test()->withHeader('Idempotency-Key', $idempotencyKey ?? (string) Str::uuid())
        ->postJson("/api/auctions/{$auction->id}/bids", $payload);
}

test('an authenticated bidder can place a winning bid on an active auction', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    $response = placeBid($auction, ['amount' => 110]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', '110.00')
        ->assertJsonPath('data.bidder_id', $bidder->id)
        ->assertJsonPath('data.auction.current_value', '110.00')
        ->assertJsonPath('data.auction.status', 'active');

    $this->assertDatabaseHas('bids', [
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 110,
        'status' => 'accepted',
    ]);

    $this->assertDatabaseHas('auctions', ['id' => $auction->id, 'current_value' => 110]);

    $this->assertDatabaseHas('bid_audit_logs', [
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'attempted_amount' => 110,
        'result' => 'accepted',
    ]);
});

test('a bid below the minimum acceptable amount is rejected and audited', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    placeBid($auction, ['amount' => 105])->assertUnprocessable();

    $this->assertDatabaseMissing('bids', ['auction_id' => $auction->id]);
    $this->assertDatabaseHas('bid_audit_logs', [
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'attempted_amount' => 105,
        'result' => 'rejected',
    ]);
});

test('the seller cannot bid on their own auction, and the attempt is audited', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->active()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['*']);

    placeBid($auction, ['amount' => 200])->assertForbidden();

    $this->assertDatabaseHas('bid_audit_logs', [
        'auction_id' => $auction->id,
        'bidder_id' => $seller->id,
        'result' => 'rejected',
    ]);
});

test('a bid on a scheduled (not yet active) auction is rejected and audited', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->create(); // default status: scheduled
    Sanctum::actingAs($bidder, ['*']);

    placeBid($auction, ['amount' => 200])->assertUnprocessable();

    $this->assertDatabaseHas('bid_audit_logs', [
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'result' => 'rejected',
    ]);
});

test('a blocked bidder cannot place a bid, and the attempt is audited', function () {
    $bidder = User::factory()->create(['is_blocked' => true]);
    $auction = Auction::factory()->active()->create();
    Sanctum::actingAs($bidder, ['*']);

    placeBid($auction, ['amount' => 200])->assertForbidden();

    $this->assertDatabaseHas('bid_audit_logs', [
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'result' => 'rejected',
    ]);
});

test('bidding on a non-existent auction returns 404 and is still audited', function () {
    $bidder = User::factory()->create();
    Sanctum::actingAs($bidder, ['*']);

    placeBid(Auction::factory()->make(['id' => 999999]), ['amount' => 200])->assertNotFound();

    $this->assertDatabaseHas('bid_audit_logs', [
        'auction_id' => 999999,
        'bidder_id' => $bidder->id,
        'result' => 'rejected',
    ]);
});

test('placing a bid requires the bid:place ability', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create();
    Sanctum::actingAs($bidder, ['profile:read']);

    placeBid($auction, ['amount' => 200])->assertForbidden();
});

test('placing a bid requires authentication', function () {
    $auction = Auction::factory()->active()->create();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 200])
        ->assertUnauthorized();
});

test('placing a bid without an Idempotency-Key header is rejected', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create();
    Sanctum::actingAs($bidder, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 200])
        ->assertStatus(400);
});

test('there is no route to cancel or delete a bid', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create();
    Sanctum::actingAs($bidder, ['*']);

    $response = placeBid($auction, ['amount' => 200]);
    $bidId = $response->json('data.id');

    $this->deleteJson("/api/auctions/{$auction->id}/bids/{$bidId}")->assertNotFound();
    $this->patchJson("/api/auctions/{$auction->id}/bids/{$bidId}")->assertNotFound();
});
