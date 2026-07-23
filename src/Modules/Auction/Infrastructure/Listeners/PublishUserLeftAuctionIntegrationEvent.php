<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\UserLeftAuction;
use App\Modules\Auction\Infrastructure\Events\UserLeftAuctionIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishUserLeftAuctionIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(UserLeftAuction $event): void
    {
        $this->publisher->publish(UserLeftAuctionIntegrationEvent::fromDomainEvent($event));
    }
}
