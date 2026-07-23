<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\UserJoinedAuction;
use App\Modules\Auction\Infrastructure\Events\UserJoinedAuctionIntegrationEvent;
use App\Shared\Infrastructure\MessageBroker\SafeIntegrationEventPublisher;

final class PublishUserJoinedAuctionIntegrationEvent
{
    public function __construct(private readonly SafeIntegrationEventPublisher $publisher)
    {
    }

    public function handle(UserJoinedAuction $event): void
    {
        $this->publisher->publish(UserJoinedAuctionIntegrationEvent::fromDomainEvent($event));
    }
}
