<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Shared\Domain\Events\DomainEvent;
use DateTimeImmutable;

final class AuctionExtended implements DomainEvent
{
    public function __construct(
        public readonly int $auctionId,
        public readonly DateTimeImmutable $newEndsAt,
        public readonly int $extensionsCount,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
