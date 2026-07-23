<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Same Log-spy strategy as BroadcastBidConsumerTest — no Broadcast::fake()
 * in this Laravel version (see that test's docblock).
 */
beforeEach(function () {
    config(['broadcasting.default' => 'log']);
    Redis::del('auction:55:viewers');
});

test('consume:viewer-count broadcasts the current Redis-backed count on a join event', function () {
    Log::spy();

    ensureConsumerQueueExists('broadcast_viewer_count', 'auction.user_joined');
    ensureConsumerQueueExists('broadcast_viewer_count', 'auction.user_left');

    Redis::sadd('auction:55:viewers', 7, 8, 9);

    publishRawIntegrationEvent('auction.user_joined', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => 55,
        'user_id' => 9,
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:viewer-count', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) {
        return str_contains($message, 'Broadcasting [viewers.updated]')
            && str_contains($message, 'presence-auction.55')
            && str_contains($message, '"viewer_count": 3');
    })->once();
});

test('consume:viewer-count also reacts to leave events on the same queue', function () {
    Log::spy();

    ensureConsumerQueueExists('broadcast_viewer_count', 'auction.user_joined');
    ensureConsumerQueueExists('broadcast_viewer_count', 'auction.user_left');

    Redis::sadd('auction:55:viewers', 7);

    publishRawIntegrationEvent('auction.user_left', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => 55,
        'user_id' => 8,
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:viewer-count', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) {
        return str_contains($message, 'Broadcasting [viewers.updated]')
            && str_contains($message, '"viewer_count": 1');
    })->once();
});
