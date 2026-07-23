<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use DomainException;

final class SellerCannotBidException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The seller cannot bid on their own auction.');
    }
}
