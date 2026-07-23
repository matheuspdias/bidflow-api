<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\AuctionClosed;
use App\Modules\Auction\Infrastructure\Events\AuctionClosedIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishAuctionClosedIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(AuctionClosed $event): void
    {
        $this->publisher->publish(AuctionClosedIntegrationEvent::fromDomainEvent($event));
    }
}
