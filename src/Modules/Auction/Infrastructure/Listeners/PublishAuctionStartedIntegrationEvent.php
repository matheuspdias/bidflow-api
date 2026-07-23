<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Infrastructure\Events\AuctionStartedIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishAuctionStartedIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(AuctionStarted $event): void
    {
        $this->publisher->publish(AuctionStartedIntegrationEvent::fromDomainEvent($event));
    }
}
