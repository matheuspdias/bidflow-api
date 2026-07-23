<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Laravel's broadcasting layer has no Broadcast::fake() in this version, so
 * these tests swap the default connection to the built-in "log" driver
 * (broadcast()'s public contract is exercised for real — channel, event
 * name, payload — just without a live Reverb/Pusher connection) and
 * intercept via Log::spy().
 */
beforeEach(function () {
    config(['broadcasting.default' => 'log']);
});

test('consume:bid-broadcast broadcasts bid.placed and auction.updated on the auction private channel', function () {
    Log::spy();

    ensureConsumerQueueExists('broadcast_bid', 'auction.bid_placed');

    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 110,
    ]);

    publishRawIntegrationEvent('auction.bid_placed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => $auction->id,
        'bid_id' => 42,
        'bidder_id' => 7,
        'amount' => '110.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:bid-broadcast', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($auction) {
        return str_contains($message, 'Broadcasting [bid.placed]')
            && str_contains($message, "private-auction.{$auction->id}")
            && str_contains($message, '"bidder_id": 7')
            && str_contains($message, '"amount": "110.00"');
    })->once();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($auction) {
        return str_contains($message, 'Broadcasting [auction.updated]')
            && str_contains($message, "private-auction.{$auction->id}")
            && str_contains($message, '"status": "active"')
            && str_contains($message, '"current_value": "110.00"');
    })->once();
});

test('consume:bid-broadcast broadcasts bid.placed even if the auction can no longer be found', function () {
    Log::spy();

    ensureConsumerQueueExists('broadcast_bid', 'auction.bid_placed');

    publishRawIntegrationEvent('auction.bid_placed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => 999999,
        'bid_id' => 1,
        'bidder_id' => 2,
        'amount' => '110.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:bid-broadcast', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message) => str_contains($message, 'Broadcasting [bid.placed]'),
    )->once();

    Log::shouldNotHaveReceived('info', [
        fn (string $message) => str_contains($message, 'Broadcasting [auction.updated]'),
    ]);
});
