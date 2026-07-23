<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function placeBidNow(Auction $auction, array $payload): \Illuminate\Testing\TestResponse
{
    return test()->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/auctions/{$auction->id}/bids", $payload);
}

beforeEach(function () {
    config([
        'auctions.anti_sniping.window_seconds' => 120,
        'auctions.anti_sniping.extension_seconds' => 120,
        'auctions.anti_sniping.max_extensions' => 2,
    ]);
});

test('a bid inside the anti-sniping window pushes ends_at forward and increments extensions_count', function () {
    $bidder = User::factory()->create();
    $originalEndsAt = now()->addSeconds(60);
    $auction = Auction::factory()->active()->create(['ends_at' => $originalEndsAt]);
    Sanctum::actingAs($bidder, ['*']);

    $response = placeBidNow($auction, ['amount' => 110]);

    $response->assertCreated();

    $auction->refresh();
    expect($auction->extensions_count)->toBe(1)
        ->and($auction->ends_at->getTimestamp())->toBe($originalEndsAt->copy()->addSeconds(120)->getTimestamp());
});

test('a bid outside the anti-sniping window does not touch ends_at', function () {
    $bidder = User::factory()->create();
    $originalEndsAt = now()->addHour();
    $auction = Auction::factory()->active()->create(['ends_at' => $originalEndsAt]);
    Sanctum::actingAs($bidder, ['*']);

    placeBidNow($auction, ['amount' => 110])->assertCreated();

    $auction->refresh();
    expect($auction->extensions_count)->toBe(0)
        ->and($auction->ends_at->getTimestamp())->toBe($originalEndsAt->getTimestamp());
});

test('extensions stop once the configured max is reached', function () {
    $auction = Auction::factory()->active()->create([
        'ends_at' => now()->addSeconds(60),
        'extensions_count' => 2,
    ]);
    $bidder = User::factory()->create();
    Sanctum::actingAs($bidder, ['*']);

    $originalEndsAt = $auction->ends_at;

    placeBidNow($auction, ['amount' => 110])->assertCreated();

    $auction->refresh();
    expect($auction->extensions_count)->toBe(2)
        ->and($auction->ends_at->getTimestamp())->toBe($originalEndsAt->getTimestamp());
});

test('an extension publishes an AuctionExtended integration event that ends up broadcasting auction.extended', function () {
    config(['broadcasting.default' => 'log']);
    Log::spy();

    ensureConsumerQueueExists('broadcast_auction_extended', 'auction.auction_extended');

    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create(['ends_at' => now()->addSeconds(60)]);
    Sanctum::actingAs($bidder, ['*']);

    placeBidNow($auction, ['amount' => 110])->assertCreated();

    $this->artisan('consume:auction-extended-broadcast', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($auction) {
        return str_contains($message, 'Broadcasting [auction.extended]')
            && str_contains($message, "presence-auction.{$auction->id}")
            && str_contains($message, '"extensions_count": 1');
    })->once();
});
