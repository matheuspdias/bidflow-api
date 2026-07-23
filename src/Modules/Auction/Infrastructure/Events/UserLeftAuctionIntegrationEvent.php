<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Events;

use App\Modules\Auction\Domain\Events\UserLeftAuction;
use App\Shared\Domain\Events\IntegrationEvent;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class UserLeftAuctionIntegrationEvent implements IntegrationEvent
{
    private function __construct(
        private readonly string $eventId,
        private readonly int $auctionId,
        private readonly int $userId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public static function fromDomainEvent(UserLeftAuction $event): self
    {
        return new self(
            eventId: (string) Str::uuid(),
            auctionId: $event->auctionId,
            userId: $event->userId,
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
        return 'auction.user_left';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'auction_id' => $this->auctionId,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
