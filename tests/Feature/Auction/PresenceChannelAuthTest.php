<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * routes/channels.php upgraded auction.{id} from a private to a presence
 * channel in Fase 8 — same auth guard (any authenticated user), but now
 * classifying a role (see ADR-0012) instead of returning a bare bool. A
 * presence auth response carries that role back as JSON-encoded
 * channel_data.
 */
test('the auction seller is classified as seller on their own auction channel', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);

    Sanctum::actingAs($seller, ['*']);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => "presence-auction.{$auction->id}",
        'socket_id' => '1234.5678',
    ])->assertOk()->assertJsonStructure(['auth', 'channel_data']);

    $channelData = json_decode($response->json('channel_data'), true);

    expect($channelData['user_info']['role'])->toBe('seller');
});

test('a user who already bid on the auction is classified as bidder', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create();

    Bid::create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 110,
        'status' => 'accepted',
    ]);

    Sanctum::actingAs($bidder, ['*']);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => "presence-auction.{$auction->id}",
        'socket_id' => '1234.5678',
    ])->assertOk();

    $channelData = json_decode($response->json('channel_data'), true);

    expect($channelData['user_info']['role'])->toBe('bidder');
});

test('a user with no relationship to the auction is classified as viewer', function () {
    $stranger = User::factory()->create();
    $auction = Auction::factory()->active()->create();

    Sanctum::actingAs($stranger, ['*']);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => "presence-auction.{$auction->id}",
        'socket_id' => '1234.5678',
    ])->assertOk();

    $channelData = json_decode($response->json('channel_data'), true);

    expect($channelData['user_info']['role'])->toBe('viewer');
});

test('subscribing to a non-existent auction is denied', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'presence-auction.999999',
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
