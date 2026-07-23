<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use DomainException;

/**
 * Raised when a bid doesn't beat the current value by at least the
 * auction's minimum increment. Unused until Auction::placeBid() is
 * implemented in Fase 4 — created now because it's a domain concept, not
 * an infrastructure detail.
 */
final class BidTooLowException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Bid does not meet the minimum required amount.');
    }
}
