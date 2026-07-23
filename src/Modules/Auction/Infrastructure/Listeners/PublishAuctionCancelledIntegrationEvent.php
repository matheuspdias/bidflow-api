<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Infrastructure\Events\AuctionCancelledIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishAuctionCancelledIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(AuctionCancelled $event): void
    {
        $this->publisher->publish(AuctionCancelledIntegrationEvent::fromDomainEvent($event));
    }
}
