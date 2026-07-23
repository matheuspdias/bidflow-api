<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Events;

use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Shared\Domain\Events\IntegrationEvent;
use App\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class BidPlacedIntegrationEvent implements IntegrationEvent
{
    private function __construct(
        private readonly string $eventId,
        private readonly int $auctionId,
        private readonly int $bidId,
        private readonly int $bidderId,
        private readonly Money $amount,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public static function fromDomainEvent(BidPlaced $event): self
    {
        return new self(
            eventId: (string) Str::uuid(),
            auctionId: $event->auctionId,
            bidId: $event->bidId,
            bidderId: $event->bidderId,
            amount: $event->amount,
            occurredAt: $event->occurredAt(),
        );
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function routingKey(): string
    {
        return 'auction.bid_placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'auction_id' => $this->auctionId,
            'bid_id' => $this->bidId,
            'bidder_id' => $this->bidderId,
            'amount' => $this->amount->amount(),
            'currency' => $this->amount->currency(),
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
