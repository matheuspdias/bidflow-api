<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Events;

use App\Modules\Auction\Domain\Events\AuctionClosed;
use App\Shared\Domain\Events\IntegrationEvent;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class AuctionClosedIntegrationEvent implements IntegrationEvent
{
    private function __construct(
        private readonly string $eventId,
        private readonly int $auctionId,
        private readonly ?int $winnerId,
        private readonly string $finalPrice,
        private readonly string $currency,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public static function fromDomainEvent(AuctionClosed $event): self
    {
        return new self(
            eventId: (string) Str::uuid(),
            auctionId: $event->auctionId,
            winnerId: $event->winnerId,
            finalPrice: $event->finalPrice->amount(),
            currency: $event->finalPrice->currency(),
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
        return 'auction.auction_closed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'auction_id' => $this->auctionId,
            'winner_id' => $this->winnerId,
            'final_price' => $this->finalPrice,
            'currency' => $this->currency,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
