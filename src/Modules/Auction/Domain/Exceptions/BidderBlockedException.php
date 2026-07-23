<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use DomainException;

final class BidderBlockedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Blocked users cannot place bids.');
    }
}
