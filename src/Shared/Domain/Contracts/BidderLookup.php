<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Auction verify facts about a bidder (e.g. before accepting a
 * bid) without depending on Modules\User internals directly.
 */
interface BidderLookup
{
    public function exists(int $bidderId): bool;

    public function isBlocked(int $bidderId): bool;
}
