<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Repositories;

use App\Modules\Auction\Domain\Aggregates\Auction;

/**
 * Framework-agnostic pagination shape — kept out of the Illuminate paginator
 * contracts so this Domain interface stays free of framework dependencies.
 */
final class AuctionPage
{
    /**
     * @param  list<Auction>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {
    }
}
