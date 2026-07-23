<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Shared\Domain\Contracts\NotificationDispatcher;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;

final class SendWonNotificationConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:auction-won-notification {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.auction_closed events and notify the winner, if any';

    public function __construct(
        RabbitMqConnectionFactory $connectionFactory,
        private readonly AuctionRepository $auctions,
        private readonly NotificationDispatcher $notifications,
    ) {
        parent::__construct($connectionFactory);
    }

    protected function consumerName(): string
    {
        return 'send_won_notification';
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
        $winnerId = $payload['winner_id'] ?? null;

        if ($winnerId === null) {
            // No sale — either nobody bid, or the reserve price was never
            // met (Auction::close() already folded both cases into a null
            // winner_id). Nobody to notify.
            return;
        }

        $auctionId = (int) $payload['auction_id'];
        $auction = $this->auctions->findById($auctionId);

        $this->notifications->dispatch((int) $winnerId, 'auction_won', [
            'auction_id' => $auctionId,
            'auction_name' => $auction?->name() ?? "Auction #{$auctionId}",
            'final_price' => (string) $payload['final_price'],
        ]);
    }
}
