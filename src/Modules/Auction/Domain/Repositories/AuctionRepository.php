<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Repositories;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;

interface AuctionRepository
{
    public function findById(int $id): ?Auction;

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
