<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Repositories;

use App\Modules\Auction\Domain\Entities\Bid;

interface BidRepository
{
    public function create(Bid $bid): Bid;

    public function hasBidderBidOn(int $auctionId, int $bidderId): bool;

    /**
     * @return list<Bid>
     */
    public function recentForAuction(int $auctionId, int $limit = 50): array;
}
