<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\DTOs;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Entities\Bid;

final class BidPlacementResult
{
    public function __construct(
        public readonly Auction $auction,
        public readonly Bid $bid,
    ) {
    }
}
