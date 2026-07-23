<?php

declare(strict_types=1);

namespace App\Modules\Auction\Domain\Repositories;

use App\Shared\Domain\ValueObjects\Money;

interface BidAuditLogRepository
{
    public function record(
        int $auctionId,
        int $bidderId,
        Money $attemptedAmount,
        ?string $ipAddress,
        ?string $userAgent,
        string $result,
        ?string $reason,
    ): void;
}
