<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Infrastructure\Broadcast\AuctionUpdatedBroadcastEvent;
use App\Modules\Auction\Infrastructure\Broadcast\BidPlacedBroadcastEvent;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;

/**
 * Broadcasts BidPlaced over Reverb — outside the lifecycle of the HTTP
 * request that placed the bid, from this background consumer instead.
 * Fires both a feed entry (bid.placed) and a state resync (auction.updated)
 * — see the broadcast event classes for why those stay decoupled.
 */
final class BroadcastBidConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:bid-broadcast {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.bid_placed events and broadcast bid.placed + auction.updated over WebSocket';

    public function __construct(
        RabbitMqConnectionFactory $connectionFactory,
        private readonly AuctionRepository $auctions,
    ) {
        parent::__construct($connectionFactory);
    }

    protected function consumerName(): string
    {
        return 'broadcast_bid';
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
        broadcast(new BidPlacedBroadcastEvent(
            auctionId: (int) $payload['auction_id'],
            bidId: (int) $payload['bid_id'],
            bidderId: (int) $payload['bidder_id'],
            amount: (string) $payload['amount'],
            placedAt: (string) $payload['occurred_at'],
        ));

        $auction = $this->auctions->findById((int) $payload['auction_id']);

        if ($auction === null) {
            return;
        }

        broadcast(new AuctionUpdatedBroadcastEvent(
            auctionId: $auction->id(),
            status: $auction->status()->value,
            currentValue: $auction->currentValue()->amount(),
            participantCount: $auction->participantCount(),
        ));
    }
}
