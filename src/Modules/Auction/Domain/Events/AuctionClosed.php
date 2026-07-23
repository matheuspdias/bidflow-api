<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Events;

use App\Shared\Domain\Events\DomainEvent;
use App\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;

final class AuctionClosed implements DomainEvent
{
    public function __construct(
        public readonly int $auctionId,
        public readonly ?int $winnerId,
        public readonly Money $finalPrice,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
