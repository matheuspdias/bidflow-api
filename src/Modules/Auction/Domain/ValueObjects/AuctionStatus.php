<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\ValueObjects;

enum AuctionStatus: string
{
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
