<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Modules\Auction\Infrastructure\Broadcast\AuctionEndedBroadcastEvent;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;

final class BroadcastAuctionEndedConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:auction-ended-broadcast {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.auction_closed events and broadcast auction.ended';

    protected function consumerName(): string
    {
        return 'broadcast_auction_ended';
    }

    protected function routingKey(): string
    {
        return 'auction.auction_closed';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function process(array $payload): void
    {
        broadcast(new AuctionEndedBroadcastEvent(
            auctionId: (int) $payload['auction_id'],
            winnerId: $payload['winner_id'] !== null ? (int) $payload['winner_id'] : null,
            finalPrice: (string) $payload['final_price'],
        ));
    }
}
