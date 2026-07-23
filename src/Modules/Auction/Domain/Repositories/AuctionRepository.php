<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Repositories;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use DateTimeImmutable;

interface AuctionRepository
{
    public function findById(int $id): ?Auction;

    /**
     * Ids of ACTIVE auctions whose ends_at has already passed — the
     * closing command's polling query. Returns bare ids, not hydrated
     * aggregates: each one still needs findByIdForUpdate() individually so
     * closing can lock and re-check status inside its own transaction (an
     * auction can't be safely closed off a snapshot that might already be
     * stale by the time the lock is acquired).
     *
     * @return list<int>
     */
    public function activeIdsEndingBefore(DateTimeImmutable $moment): array;

    /**
     * Locks the row (SELECT ... FOR UPDATE) for the lifetime of the current
     * transaction. Unused until Fase 4, where bid placement needs it to
     * serialize concurrent bids on the same auction.
     */
    public function findByIdForUpdate(int $id): ?Auction;

    public function paginate(int $page, int $perPage, ?AuctionStatus $status = null, ?int $categoryId = null): AuctionPage;

    public function create(Auction $auction): Auction;

    public function save(Auction $auction): void;
}
