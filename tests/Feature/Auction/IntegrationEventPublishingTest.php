<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Declares a private, auto-deleting queue bound to the real domain_events
 * exchange for the given routing key. Must be called — and the binding
 * confirmed — *before* the action that publishes the message: a topic
 * exchange drops anything published before a matching binding exists, it
 * doesn't queue it up for a consumer that shows up later.
 *
 * @return array{0: AMQPStreamConnection, 1: AMQPChannel, 2: string}
 */
function bindTemporaryQueue(string $routingKey): array
{
    $connection = app(RabbitMqConnectionFactory::class)->make();
    $channel = $connection->channel();

    [$queue] = $channel->queue_declare('', false, false, true, true);
    $channel->queue_bind($queue, config('rabbitmq.exchange'), $routingKey);

    return [$connection, $channel, $queue];
}

function awaitOneMessage(AMQPChannel $channel, string $queue, float $timeoutSeconds = 5.0): ?array
{
    $received = null;

    $channel->basic_consume($queue, '', false, true, false, false, function ($message) use (&$received) {
        $received = json_decode($message->getBody(), true);
    });

    $deadline = microtime(true) + $timeoutSeconds;

    while ($received === null && microtime(true) < $deadline) {
        $channel->wait(null, false, max(0.1, $deadline - microtime(true)));
    }

    return $received;
}

test('placing a bid publishes a BidPlaced integration event to the real exchange', function () {
    [$connection, $channel, $queue] = bindTemporaryQueue('auction.bid_placed');

    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 110])
        ->assertCreated();

    $message = awaitOneMessage($channel, $queue);
    $channel->close();
    $connection->close();

    expect($message)->not->toBeNull()
        ->and($message['auction_id'])->toBe($auction->id)
        ->and($message['bidder_id'])->toBe($bidder->id)
        ->and($message['amount'])->toBe('110.00')
        ->and($message)->toHaveKey('event_id')
        ->and($message)->toHaveKey('bid_id');
});

test('activating an auction publishes an AuctionStarted integration event', function () {
    [$connection, $channel, $queue] = bindTemporaryQueue('auction.auction_started');

    $seller = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/activate")->assertOk();

    $message = awaitOneMessage($channel, $queue);
    $channel->close();
    $connection->close();

    expect($message)->not->toBeNull()
        ->and($message['auction_id'])->toBe($auction->id);
});

test('a broker outage does not fail the bid — it is recorded for replay instead', function () {
    // Point at a port nothing is listening on, forcing a fast connection
    // failure without waiting for a real timeout.
    config(['rabbitmq.port' => 1]);

    $bidder = User::factory()->create();
    $auction = Auction::factory()->active()->create([
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);
    Sanctum::actingAs($bidder, ['*']);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 110])
        ->assertCreated();

    $this->assertDatabaseHas('bids', ['auction_id' => $auction->id, 'bidder_id' => $bidder->id]);
    $this->assertDatabaseHas('failed_integration_events', ['routing_key' => 'auction.bid_placed']);
});
