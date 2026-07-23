<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Aggregates;

use App\Modules\Auction\Domain\Entities\Bid;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionClosed;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Domain\Exceptions\AuctionClosedException;
use App\Modules\Auction\Domain\Exceptions\BidTooLowException;
use App\Modules\Auction\Domain\Exceptions\InvalidAuctionStatusTransitionException;
use App\Modules\Auction\Domain\Exceptions\SellerCannotBidException;
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
        private ?int $highestBidId = null,
        private int $extensionsCount = 0,
        private ?int $winnerId = null,
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
        ?int $highestBidId = null,
        int $extensionsCount = 0,
        ?int $winnerId = null,
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
            highestBidId: $highestBidId,
            extensionsCount: $extensionsCount,
            winnerId: $winnerId,
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
     * Transitions ACTIVE to CLOSED and determines the winner — called only
     * by the scheduled closing command, never a direct HTTP action (see
     * README's domain model section). $winnerId/$winningAmount describe the
     * current highest bid, if any; the caller resolves them via BidRepository
     * since the aggregate itself has no bidder identity beyond a bid id.
     *
     * A reserve_price that the highest bid never reached means no sale: the
     * event still fires (the auction still closes), but with a null winner.
     */
    public function close(?int $winnerId, ?Money $winningAmount): void
    {
        if ($this->status !== AuctionStatus::ACTIVE) {
            throw InvalidAuctionStatusTransitionException::from($this->status, AuctionStatus::CLOSED);
        }

        $this->status = AuctionStatus::CLOSED;

        $reserveMet = $this->reservePrice === null
            || ($winningAmount !== null && $winningAmount->isGreaterThanOrEqualTo($this->reservePrice));

        $finalWinnerId = $reserveMet ? $winnerId : null;
        $this->winnerId = $finalWinnerId;

        $this->record(new AuctionClosed($this->requireId(), $finalWinnerId, $this->currentValue, new DateTimeImmutable()));
    }

    /**
     * Validates and applies a bid, returning the not-yet-persisted Bid
     * entity. Bidder-blocked checks are deliberately not done here — that
     * fact belongs to Modules\User, checked by the caller (PlaceBidUseCase)
     * via the Shared\Domain\Contracts\BidderLookup contract before this
     * method is ever invoked.
     *
     * $isNewParticipant is supplied by the caller (a query against bid
     * history) rather than computed here, since the aggregate has no way to
     * know about previous bids beyond its own in-memory state.
     */
    public function placeBid(int $bidderId, Money $amount, bool $isNewParticipant): Bid
    {
        if ($this->status !== AuctionStatus::ACTIVE) {
            throw new AuctionClosedException();
        }

        if ($bidderId === $this->sellerId) {
            throw new SellerCannotBidException();
        }

        $minimumAcceptable = $this->currentValue->add($this->minimumIncrement);

        if ($amount->isLessThan($minimumAcceptable)) {
            throw new BidTooLowException();
        }

        $this->currentValue = $amount;

        if ($isNewParticipant) {
            $this->participantCount++;
        }

        return Bid::place($this->requireId(), $bidderId, $amount);
    }

    /**
     * Raises BidPlaced once the bid has a persisted id — placeBid() itself
     * can't do this, since the id is only assigned by the repository after
     * the INSERT, which happens after placeBid() returns.
     */
    public function recordBidPlaced(Bid $bid): void
    {
        $bidId = $bid->id() ?? throw new LogicException('Cannot record BidPlaced for a bid that has not been persisted yet.');

        $this->record(new BidPlaced($this->requireId(), $bidId, $bid->bidderId(), $bid->amount(), $bid->placedAt()));
    }

    /**
     * Anti-sniping: a bid landing inside windowSeconds of ends_at pushes
     * ends_at forward by extensionSeconds, capped at maxExtensions per
     * auction (ADR-0014) — otherwise a bidder could prolong an auction
     * indefinitely by bidding in the last second of every extension.
     * Called by PlaceBidUseCase right after placeBid() succeeds, inside the
     * same transaction — a rejected bid never reaches here.
     *
     * Returns whether an extension happened, so the caller knows whether to
     * treat AuctionExtended as having been raised.
     */
    public function extendIfWithinAntiSnipingWindow(
        DateTimeImmutable $now,
        int $windowSeconds,
        int $extensionSeconds,
        int $maxExtensions,
    ): bool {
        if ($this->extensionsCount >= $maxExtensions) {
            return false;
        }

        $secondsRemaining = $this->schedule->end->getTimestamp() - $now->getTimestamp();

        if ($secondsRemaining < 0 || $secondsRemaining > $windowSeconds) {
            return false;
        }

        $this->schedule = DateRange::of($this->schedule->start, $this->schedule->end->modify("+{$extensionSeconds} seconds"));
        $this->extensionsCount++;

        $this->record(new AuctionExtended($this->requireId(), $this->schedule->end, $this->extensionsCount, $now));

        return true;
    }

    public function markHighestBid(int $bidId): void
    {
        $this->highestBidId = $bidId;
    }

    public function highestBidId(): ?int
    {
        return $this->highestBidId;
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

    public function extensionsCount(): int
    {
        return $this->extensionsCount;
    }

    public function winnerId(): ?int
    {
        return $this->winnerId;
    }
}
