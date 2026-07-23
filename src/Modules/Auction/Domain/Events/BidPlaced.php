<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Shared\Domain\Events\DomainEvent;
use App\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;

final class BidPlaced implements DomainEvent
{
    public function __construct(
        public readonly int $auctionId,
        public readonly int $bidderId,
        public readonly Money $amount,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
