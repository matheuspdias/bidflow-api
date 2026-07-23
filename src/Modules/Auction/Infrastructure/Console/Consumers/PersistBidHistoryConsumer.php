<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use Illuminate\Support\Facades\DB;

/**
 * Builds a denormalized read model (bid_history), separate from the
 * transactional `bids` table — a CQRS split, not a duplicate for its own
 * sake (see ADR-0010).
 */
final class PersistBidHistoryConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:bid-history {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.bid_placed events and populate the denormalized bid_history read model';

    protected function consumerName(): string
    {
        return 'persist_bid_history';
    }

    protected function routingKey(): string
    {
        return 'auction.bid_placed';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function process(array $payload): void
    {
        // No duplicate-key handling needed here: RabbitMqConsumerCommand's
        // atomic claim() already guarantees this runs at most once per
        // (event_id, consumer_name), even under two racing instances.
        DB::table('bid_history')->insert([
            'event_id' => $payload['event_id'],
            'auction_id' => $payload['auction_id'],
            'bidder_id' => $payload['bidder_id'],
            'amount' => $payload['amount'],
            'occurred_at' => $payload['occurred_at'],
            'created_at' => now(),
        ]);
    }
}
