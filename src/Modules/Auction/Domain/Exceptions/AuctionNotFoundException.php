<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use DomainException;

final class AuctionNotFoundException extends DomainException
{
    public function __construct(int $auctionId)
    {
        parent::__construct("Auction [{$auctionId}] was not found.");
    }
}
