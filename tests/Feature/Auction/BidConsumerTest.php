<?php

use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Support\AlwaysFailingTestConsumer;

test('consume:auction-stats increments Redis counters for a bid_placed event', function () {
    ensureConsumerQueueExists('update_auction_stats', 'auction.bid_placed');

    $auctionId = random_int(100000, 999999);

    publishRawIntegrationEvent('auction.bid_placed', [
        'event_id' => (string) Str::uuid(),
        'auction_id' => $auctionId,
        'bid_id' => 1,
        'bidder_id' => 2,
        'amount' => '150.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ]);

    $this->artisan('consume:auction-stats', ['--limit' => 1, '--timeout' => 5])->assertSuccessful();

    expect((int) Redis::get("stats:auctions:{$auctionId}:bid_count"))->toBe(1);
});

test('consume:bid-history persists exactly one row even when the same event is delivered twice', function () {
    ensureConsumerQueueExists('persist_bid_history', 'auction.bid_placed');

    $eventId = (string) Str::uuid();
    $auctionId = random_int(100000, 999999);

    $payload = [
        'event_id' => $eventId,
        'auction_id' => $auctionId,
        'bid_id' => 1,
        'bidder_id' => 2,
        'amount' => '150.00',
        'currency' => 'USD',
        'occurred_at' => now()->toAtomString(),
    ];

    // Simulate at-least-once redelivery: the exact same event published
    // twice, as if a consumer crashed after processing but before acking.
    publishRawIntegrationEvent('auction.bid_placed', $payload);
    publishRawIntegrationEvent('auction.bid_placed', $payload);

    $this->artisan('consume:bid-history', ['--limit' => 2, '--timeout' => 5])->assertSuccessful();

    $this->assertDatabaseCount('bid_history', 1);
    $this->assertDatabaseHas('bid_history', ['event_id' => $eventId, 'auction_id' => $auctionId]);

    $processedRows = DB::table('processed_events')
        ->where('event_id', $eventId)
        ->where('consumer_name', 'persist_bid_history')
        ->count();

    expect($processedRows)->toBe(1);
});

test('a consumer that keeps failing exhausts its retries and dead-letters the message', function () {
    ensureConsumerQueueExists('test_always_failing', 'test.always_failing');

    $connection = app(RabbitMqConnectionFactory::class)->make();
    $dlxChannel = $connection->channel();
    [$dlxQueue] = $dlxChannel->queue_declare('', false, false, true, true);
    $dlxChannel->queue_bind($dlxQueue, config('rabbitmq.exchange').'.dlx', '');

    publishRawIntegrationEvent('test.always_failing', [
        'event_id' => (string) Str::uuid(),
        'note' => 'this payload is designed to always fail processing',
    ]);

    $consumer = app(AlwaysFailingTestConsumer::class);
    $consumer->setLaravel(app());
    $consumer->run(new ArrayInput(['--limit' => 4, '--timeout' => 5]), new NullOutput());

    $deadLettered = null;
    $dlxChannel->basic_consume($dlxQueue, '', false, true, false, false, function ($message) use (&$deadLettered) {
        $deadLettered = json_decode($message->getBody(), true);
    });

    $deadline = microtime(true) + 5.0;
    while ($deadLettered === null && microtime(true) < $deadline) {
        $dlxChannel->wait(null, false, max(0.1, $deadline - microtime(true)));
    }

    $dlxChannel->close();
    $connection->close();

    expect($deadLettered)->not->toBeNull()
        ->and($deadLettered['note'])->toBe('this payload is designed to always fail processing');

    $this->assertDatabaseMissing('processed_events', ['consumer_name' => 'test_always_failing']);
});
