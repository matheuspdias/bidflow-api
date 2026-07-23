<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Infrastructure\Events\BidPlacedIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishBidPlacedIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(BidPlaced $event): void
    {
        $this->publisher->publish(BidPlacedIntegrationEvent::fromDomainEvent($event));
    }
}
