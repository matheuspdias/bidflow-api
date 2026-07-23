<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use Illuminate\Support\Facades\Redis;

final class UpdateAuctionStatsConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:auction-stats {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.bid_placed events and update lightweight bid stats in Redis';

    protected function consumerName(): string
    {
        return 'update_auction_stats';
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
        Redis::incr("stats:auctions:{$payload['auction_id']}:bid_count");
        Redis::incr('stats:bids:total');
    }
}
