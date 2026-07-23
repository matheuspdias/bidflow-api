<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Aggregates;

use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Exceptions\InvalidAuctionStatusTransitionException;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use App\Shared\Domain\Contracts\UserIdentity;
use App\Shared\Domain\Events\DomainEvent;
use App\Shared\Domain\ValueObjects\DateRange;
use App\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;
use LogicException;

/**
 * Aggregate root for a single auction. Bid (Fase 4) is an entity of this
 * aggregate, not a module of its own — it has no lifecycle independent of
 * the auction it belongs to.
 */
final class Auction
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    private function __construct(
        private ?int $id,
        private readonly int $sellerId,
        private int $categoryId,
        private string $name,
        private string $description,
        private readonly Money $startingBid,
        private readonly Money $minimumIncrement,
        private readonly ?Money $buyNowPrice,
        private readonly ?Money $reservePrice,
        private AuctionStatus $status,
        private DateRange $schedule,
        private Money $currentValue,
        private int $participantCount,
        private int $viewCount,
    ) {
    }

    public static function schedule(
        int $sellerId,
        int $categoryId,
        string $name,
        string $description,
        Money $startingBid,
        Money $minimumIncrement,
        ?Money $buyNowPrice,
        ?Money $reservePrice,
        DateRange $schedule,
    ): self {
        return new self(
            id: null,
            sellerId: $sellerId,
            categoryId: $categoryId,
            name: $name,
            description: $description,
            startingBid: $startingBid,
            minimumIncrement: $minimumIncrement,
            buyNowPrice: $buyNowPrice,
            reservePrice: $reservePrice,
            status: AuctionStatus::SCHEDULED,
            schedule: $schedule,
            currentValue: $startingBid,
            participantCount: 0,
            viewCount: 0,
        );
    }

    /**
     * Rehydrates an existing auction from storage. Used by the repository —
     * never by application code creating a brand-new auction (use schedule()
     * for that instead).
     */
    public static function reconstitute(
        int $id,
        int $sellerId,
        int $categoryId,
        string $name,
        string $description,
        Money $startingBid,
        Money $minimumIncrement,
        ?Money $buyNowPrice,
        ?Money $reservePrice,
        AuctionStatus $status,
        DateRange $schedule,
        Money $currentValue,
        int $participantCount,
        int $viewCount,
    ): self {
        return new self(
            id: $id,
            sellerId: $sellerId,
            categoryId: $categoryId,
            name: $name,
            description: $description,
            startingBid: $startingBid,
            minimumIncrement: $minimumIncrement,
            buyNowPrice: $buyNowPrice,
            reservePrice: $reservePrice,
            status: $status,
            schedule: $schedule,
            currentValue: $currentValue,
            participantCount: $participantCount,
            viewCount: $viewCount,
        );
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException('Auction id is already assigned.');
        }

        $this->id = $id;
    }

    public function updateDetails(string $name, string $description, int $categoryId, DateRange $schedule): void
    {
        if ($this->status !== AuctionStatus::SCHEDULED) {
            throw InvalidAuctionStatusTransitionException::cannotEditAfterActivation($this->status);
        }

        $this->name = $name;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->schedule = $schedule;
    }

    public function activate(): void
    {
        if ($this->status !== AuctionStatus::SCHEDULED) {
            throw InvalidAuctionStatusTransitionException::from($this->status, AuctionStatus::ACTIVE);
        }

        $this->status = AuctionStatus::ACTIVE;
        $this->record(new AuctionStarted($this->requireId(), new DateTimeImmutable()));
    }

    public function cancel(): void
    {
        if (! in_array($this->status, [AuctionStatus::SCHEDULED, AuctionStatus::ACTIVE], true)) {
            throw InvalidAuctionStatusTransitionException::from($this->status, AuctionStatus::CANCELLED);
        }

        $this->status = AuctionStatus::CANCELLED;
        $this->record(new AuctionCancelled($this->requireId(), new DateTimeImmutable()));
    }

    /**
     * Full bidding logic (invariants, current value, anti-sniping) lands in
     * Fase 4. The signature exists now because Auction owns Bid, but calling
     * this before then is a programming error, not a business rule.
     */
    public function placeBid(int $bidderId, Money $amount): void
    {
        throw new LogicException('Auction::placeBid() is not implemented until Fase 4.');
    }

    public function isOwnedBy(UserIdentity $identity): bool
    {
        return $identity->id() === $this->sellerId;
    }

    public function incrementViewCount(): void
    {
        $this->viewCount++;
    }

    /**
     * @return list<DomainEvent>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    private function requireId(): int
    {
        return $this->id ?? throw new LogicException('Auction has not been persisted yet.');
    }

    public function id(): int
    {
        return $this->requireId();
    }

    public function sellerId(): int
    {
        return $this->sellerId;
    }

    public function categoryId(): int
    {
        return $this->categoryId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function startingBid(): Money
    {
        return $this->startingBid;
    }

    public function minimumIncrement(): Money
    {
        return $this->minimumIncrement;
    }

    public function buyNowPrice(): ?Money
    {
        return $this->buyNowPrice;
    }

    public function reservePrice(): ?Money
    {
        return $this->reservePrice;
    }

    public function status(): AuctionStatus
    {
        return $this->status;
    }

    public function dateRange(): DateRange
    {
        return $this->schedule;
    }

    public function currentValue(): Money
    {
        return $this->currentValue;
    }

    public function participantCount(): int
    {
        return $this->participantCount;
    }

    public function viewCount(): int
    {
        return $this->viewCount;
    }
}
