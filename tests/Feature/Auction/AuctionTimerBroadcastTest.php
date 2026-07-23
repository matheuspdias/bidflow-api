<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use Illuminate\Support\Facades\Log;

/**
 * Same Log-spy strategy as the other broadcast tests — no Broadcast::fake()
 * in this Laravel version.
 */
beforeEach(function () {
    config(['broadcasting.default' => 'log', 'auctions.timer.broadcast_window_seconds' => 300]);
});

test('auctions:broadcast-timer broadcasts timer.updated only for active auctions inside the window', function () {
    Log::spy();

    $endingSoon = Auction::factory()->active()->create(['ends_at' => now()->addSeconds(60)]);
    $endingLater = Auction::factory()->active()->create(['ends_at' => now()->addHours(2)]);

    $this->artisan('auctions:broadcast-timer', ['--iterations' => 1])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($endingSoon) {
        return str_contains($message, 'Broadcasting [timer.updated]')
            && str_contains($message, "presence-auction.{$endingSoon->id}");
    })->once();

    Log::shouldNotHaveReceived('info', [
        fn (string $message) => str_contains($message, "presence-auction.{$endingLater->id}"),
    ]);
});

test('auctions:broadcast-timer reports zero seconds remaining once ends_at has passed', function () {
    Log::spy();

    $justEnded = Auction::factory()->active()->create(['ends_at' => now()->subSecond()]);

    $this->artisan('auctions:broadcast-timer', ['--iterations' => 1])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($justEnded) {
        return str_contains($message, "presence-auction.{$justEnded->id}")
            && str_contains($message, '"seconds_remaining": 0');
    })->once();
});
