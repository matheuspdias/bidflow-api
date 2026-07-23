<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use DomainException;

final class NotAuctionOwnerException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You are not the owner of this auction.');
    }
}
