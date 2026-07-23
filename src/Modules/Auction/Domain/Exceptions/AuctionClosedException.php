<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use DomainException;

/**
 * Raised when a bid is attempted against an auction that isn't ACTIVE.
 * Unused until Auction::placeBid() is implemented in Fase 4 — created now
 * because it's a domain concept, not an infrastructure detail.
 */
final class AuctionClosedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This auction is not open for bidding.');
    }
}
