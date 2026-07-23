<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Modules\Auction\Infrastructure\Broadcast\AuctionExtendedBroadcastEvent;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;

final class BroadcastAuctionExtendedConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:auction-extended-broadcast {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.auction_extended events and broadcast auction.extended';

    protected function consumerName(): string
    {
        return 'broadcast_auction_extended';
    }

    protected function routingKey(): string
    {
        return 'auction.auction_extended';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function process(array $payload): void
    {
        broadcast(new AuctionExtendedBroadcastEvent(
            auctionId: (int) $payload['auction_id'],
            newEndsAt: (string) $payload['new_ends_at'],
            extensionsCount: (int) $payload['extensions_count'],
        ));
    }
}
