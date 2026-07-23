<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Repositories;

use App\Modules\Auction\Domain\Repositories\BidAuditLogRepository;
use App\Shared\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

final class EloquentBidAuditLogRepository implements BidAuditLogRepository
{
    public function record(
        int $auctionId,
        int $bidderId,
        Money $attemptedAmount,
        ?string $ipAddress,
        ?string $userAgent,
        string $result,
        ?string $reason,
    ): void {
        DB::table('bid_audit_logs')->insert([
            'auction_id' => $auctionId,
            'bidder_id' => $bidderId,
            'attempted_amount' => $attemptedAmount->amount(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'result' => $result,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
