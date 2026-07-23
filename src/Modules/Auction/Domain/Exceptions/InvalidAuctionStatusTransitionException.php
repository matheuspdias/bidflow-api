<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Exceptions;

use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use DomainException;

final class InvalidAuctionStatusTransitionException extends DomainException
{
    public static function from(AuctionStatus $from, AuctionStatus $to): self
    {
        return new self("Cannot transition auction from [{$from->value}] to [{$to->value}].");
    }

    public static function cannotEditAfterActivation(AuctionStatus $current): self
    {
        return new self("Cannot edit auction details while status is [{$current->value}].");
    }
}
