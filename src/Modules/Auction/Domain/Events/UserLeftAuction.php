<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Shared\Domain\Events\DomainEvent;
use DateTimeImmutable;

/**
 * See UserJoinedAuction — same rationale, mirrored for the leave side.
 */
final class UserLeftAuction implements DomainEvent
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
