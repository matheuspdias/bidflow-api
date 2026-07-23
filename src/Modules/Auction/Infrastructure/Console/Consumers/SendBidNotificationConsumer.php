<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Shared\Domain\Contracts\NotificationDispatcher;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;

final class SendBidNotificationConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:bid-notifications {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.bid_placed events to notify the previous highest bidder they were outbid';

    public function __construct(
        RabbitMqConnectionFactory $connectionFactory,
        private readonly AuctionRepository $auctions,
        private readonly BidRepository $bids,
        private readonly NotificationDispatcher $notifications,
    ) {
        parent::__construct($connectionFactory);
    }

    protected function consumerName(): string
    {
        return 'send_bid_notification';
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
        $auctionId = (int) $payload['auction_id'];
        $newBidderId = (int) $payload['bidder_id'];
        $newBidId = (int) $payload['bid_id'];

        // Bids strictly increase (amount >= current_value + minimum_increment,
        // enforced by Auction::placeBid()), so the most recent bid other
        // than this one — if there is one — is exactly the bidder this bid
        // just outbid.
        $previousHighest = null;

        foreach ($this->bids->recentForAuction($auctionId, 5) as $bid) {
            if ($bid->id() !== $newBidId) {
                $previousHighest = $bid;

                break;
            }
        }

        if ($previousHighest === null || $previousHighest->bidderId() === $newBidderId) {
            return;
        }

        $auction = $this->auctions->findById($auctionId);

        $this->notifications->dispatch($previousHighest->bidderId(), 'outbid', [
            'auction_id' => $auctionId,
            'auction_name' => $auction?->name() ?? "Auction #{$auctionId}",
            'new_amount' => (string) $payload['amount'],
        ]);
    }
}
