<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Entities;

use App\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;
use LogicException;

/**
 * Child entity of the Auction aggregate — has no lifecycle, repository, or
 * module of its own (see ADR-0001). Never mutated or cancelled once placed.
 */
final class Bid
{
    private function __construct(
        private ?int $id,
        private readonly int $auctionId,
        private readonly int $bidderId,
        private readonly Money $amount,
        private readonly DateTimeImmutable $placedAt,
    ) {
    }

    public static function place(int $auctionId, int $bidderId, Money $amount): self
    {
        return new self(null, $auctionId, $bidderId, $amount, new DateTimeImmutable());
    }

    public static function reconstitute(int $id, int $auctionId, int $bidderId, Money $amount, DateTimeImmutable $placedAt): self
    {
        return new self($id, $auctionId, $bidderId, $amount, $placedAt);
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException('Bid id is already assigned.');
        }

        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function auctionId(): int
    {
        return $this->auctionId;
    }

    public function bidderId(): int
    {
        return $this->bidderId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function placedAt(): DateTimeImmutable
    {
        return $this->placedAt;
    }
}
