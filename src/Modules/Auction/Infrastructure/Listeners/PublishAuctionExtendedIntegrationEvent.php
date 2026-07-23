<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Infrastructure\Events\AuctionExtendedIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishAuctionExtendedIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(AuctionExtended $event): void
    {
        $this->publisher->publish(AuctionExtendedIntegrationEvent::fromDomainEvent($event));
    }
}
