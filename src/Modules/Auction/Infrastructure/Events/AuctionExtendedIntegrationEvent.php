<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Events;

use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Shared\Domain\Events\IntegrationEvent;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class AuctionExtendedIntegrationEvent implements IntegrationEvent
{
    private function __construct(
        private readonly string $eventId,
        private readonly int $auctionId,
        private readonly DateTimeImmutable $newEndsAt,
        private readonly int $extensionsCount,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public static function fromDomainEvent(AuctionExtended $event): self
    {
        return new self(
            eventId: (string) Str::uuid(),
            auctionId: $event->auctionId,
            newEndsAt: $event->newEndsAt,
            extensionsCount: $event->extensionsCount,
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
        return 'auction.auction_extended';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'auction_id' => $this->auctionId,
            'new_ends_at' => $this->newEndsAt->format(DATE_ATOM),
            'extensions_count' => $this->extensionsCount,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
