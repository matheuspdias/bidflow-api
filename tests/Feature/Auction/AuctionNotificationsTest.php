<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid;
use App\Modules\Notification\Infrastructure\Mail\NotificationMail;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

test('consume:auction-won-notification creates a notification and queues an email for the winner', function () {
    Mail::fake();
    config(['broadcasting.default' => 'log']);
    Log::spy();

    ensureConsumerQueueExists('send_won_notification', 'auction.auction_closed');

    $winner = User::factory()->create();
    $auction = Auction::factory()->create(['name' => 'Vintage Camera']);

    publishRawIntegrationEvent('auction.auction_closed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => $auction->id,
        'winner_id' => $winner->id,
        'final_price' => '250.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:auction-won-notification', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $winner->id,
        'type' => 'auction_won',
    ]);

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($winner->email));

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($winner) {
        return str_contains($message, 'Broadcasting [notification.created]')
            && str_contains($message, "private-App.Models.User.{$winner->id}")
            && str_contains($message, '"type": "auction_won"');
    })->once();
});

test('consume:auction-won-notification does nothing when the auction had no winner', function () {
    Mail::fake();

    ensureConsumerQueueExists('send_won_notification', 'auction.auction_closed');

    $auction = Auction::factory()->create();

    publishRawIntegrationEvent('auction.auction_closed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => $auction->id,
        'winner_id' => null,
        'final_price' => '130.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:auction-won-notification', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    $this->assertDatabaseCount('notifications', 0);
    Mail::assertNothingQueued();
});

test('consume:bid-notifications notifies the previous highest bidder they were outbid', function () {
    Mail::fake();
    config(['broadcasting.default' => 'log']);
    Log::spy();

    ensureConsumerQueueExists('send_bid_notification', 'auction.bid_placed');

    $firstBidder = User::factory()->create();
    $secondBidder = User::factory()->create();
    $auction = Auction::factory()->active()->create(['name' => 'Vintage Camera']);

    Bid::create([
        'auction_id' => $auction->id,
        'bidder_id' => $firstBidder->id,
        'amount' => 110,
        'status' => 'accepted',
    ]);

    // The second bid, freshly placed — this is the one the integration
    // event describes.
    $secondBid = Bid::create([
        'auction_id' => $auction->id,
        'bidder_id' => $secondBidder->id,
        'amount' => 120,
        'status' => 'accepted',
    ]);

    publishRawIntegrationEvent('auction.bid_placed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => $auction->id,
        'bid_id' => $secondBid->id,
        'bidder_id' => $secondBidder->id,
        'amount' => '120.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:bid-notifications', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $firstBidder->id,
        'type' => 'outbid',
    ]);

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($firstBidder->email));

    $this->assertDatabaseMissing('notifications', ['user_id' => $secondBidder->id]);

    Log::shouldHaveReceived('info')->withArgs(function (string $message) use ($firstBidder) {
        return str_contains($message, 'Broadcasting [notification.created]')
            && str_contains($message, "private-App.Models.User.{$firstBidder->id}");
    })->once();
});

test('consume:bid-notifications does nothing for the very first bid on an auction', function () {
    Mail::fake();

    ensureConsumerQueueExists('send_bid_notification', 'auction.bid_placed');

    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create();

    $bid = Bid::create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 110,
        'status' => 'accepted',
    ]);

    publishRawIntegrationEvent('auction.bid_placed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => $auction->id,
        'bid_id' => $bid->id,
        'bidder_id' => $bidder->id,
        'amount' => '110.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:bid-notifications', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    $this->assertDatabaseCount('notifications', 0);
    Mail::assertNothingQueued();
});
