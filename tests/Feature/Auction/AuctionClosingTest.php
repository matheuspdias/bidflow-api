<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Log;

test('auctions:close-ended closes an ended active auction and declares the highest bidder the winner', function () {
    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
        'current_value' => 130,
        'reserve_price' => null,
    ]);
    $bid = Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 130, 'status' => 'accepted']);
    $auction->update(['highest_bid_id' => $bid->id]);

    $this->artisan('auctions:close-ended', ['--iterations' => 1])->assertSuccessful();

    expect($auction->fresh()->status)->toBe('closed');
});

test('auctions:close-ended declares no winner when the reserve price was never met', function () {
    config(['broadcasting.default' => 'log']);
    Log::spy();

    ensureConsumerQueueExists('broadcast_auction_ended', 'auction.auction_closed');

    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
        'current_value' => 130,
        'reserve_price' => 500,
    ]);
    $bid = Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 130, 'status' => 'accepted']);
    $auction->update(['highest_bid_id' => $bid->id]);

    $this->artisan('auctions:close-ended', ['--iterations' => 1])->assertSuccessful();
    $this->artisan('consume:auction-ended-broadcast', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    expect($auction->fresh()->status)->toBe('closed');

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($auction) {
        return str_contains($message, 'Broadcasting [auction.ended]')
            && str_contains($message, "presence-auction.{$auction->id}")
            && str_contains($message, '"winner_id": null');
    })->once();
});

test('auctions:close-ended leaves auctions that have not ended yet untouched', function () {
    $auction = Auction::factory()->active()->create(['ends_at' => now()->addHour()]);

    $this->artisan('auctions:close-ended', ['--iterations' => 1])->assertSuccessful();

    expect($auction->fresh()->status)->toBe('active');
});

test('auctions:close-ended publishes an AuctionClosed integration event with the winner id', function () {
    config(['broadcasting.default' => 'log']);
    Log::spy();

    ensureConsumerQueueExists('broadcast_auction_ended', 'auction.auction_closed');

    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->subMinute(),
        'current_value' => 130,
        'reserve_price' => null,
    ]);
    $bid = Bid::create(['auction_id' => $auction->id, 'bidder_id' => $bidder->id, 'amount' => 130, 'status' => 'accepted']);
    $auction->update(['highest_bid_id' => $bid->id]);

    $this->artisan('auctions:close-ended', ['--iterations' => 1])->assertSuccessful();
    $this->artisan('consume:auction-ended-broadcast', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($auction, $bidder) {
        return str_contains($message, 'Broadcasting [auction.ended]')
            && str_contains($message, "presence-auction.{$auction->id}")
            && str_contains($message, "\"winner_id\": {$bidder->id}");
    })->once();
});
