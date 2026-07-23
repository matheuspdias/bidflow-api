<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Shared\Domain\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Unlike BidPlaced/AuctionStarted, this isn't pulled from an aggregate's
 * pullDomainEvents() — presence isn't part of the Auction's consistency
 * boundary. It's dispatched directly (see
 * Infrastructure\Listeners\TrackPresenceChannelMembership) the moment the
 * Reverb server itself observes a presence-channel join, so it still
 * implements DomainEvent for the same translation-to-IntegrationEvent path
 * every other in-process event goes through (ADR-0012).
 */
final class UserJoinedAuction implements DomainEvent
{
    public function __construct(
        public readonly int $auctionId,
        public readonly int $userId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
