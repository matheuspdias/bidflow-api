<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Repositories;

use App\Modules\Auction\Domain\Entities\Bid;

interface BidRepository
{
    public function create(Bid $bid): Bid;

    public function findById(int $id): ?Bid;

    public function hasBidderBidOn(int $auctionId, int $bidderId): bool;

    /**
     * @return list<Bid>
     */
    public function recentForAuction(int $auctionId, int $limit = 50): array;

    /**
     * Every bid after $afterId, oldest first — the reconnection gap-fill
     * query (Fase 13): a client back online after a drop asks for exactly
     * what it missed by id, not "the last N" (recentForAuction()), since
     * the gap could be smaller or larger than any fixed recent-history
     * window.
     *
     * @return list<Bid>
     */
    public function afterId(int $auctionId, int $afterId, int $limit = 200): array;
}
