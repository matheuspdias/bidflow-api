<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Adapters;

use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use App\Modules\Auction\Infrastructure\Persistence\Models\Auction as AuctionModel;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid as BidModel;
use App\Shared\Domain\Contracts\BusinessMetricsLookup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

final class BusinessMetricsLookupAdapter implements BusinessMetricsLookup
{
    public function current(): array
    {
        $countsByStatus = AuctionModel::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $auctionsByStatus = [];
        foreach (AuctionStatus::cases() as $status) {
            $auctionsByStatus[$status->value] = (int) ($countsByStatus[$status->value] ?? 0);
        }

        $activeAuctionIds = AuctionModel::query()
            ->where('status', AuctionStatus::ACTIVE->value)
            ->pluck('id');

        $liveViewersTotal = $activeAuctionIds->sum(
            static fn (int $auctionId): int => (int) Redis::scard("auction:{$auctionId}:viewers"),
        );

        return [
            'auctions' => $auctionsByStatus,
            'total_bids' => BidModel::query()->count(),
            // number_format, not a bare (string) cast: Eloquent's sum()
            // returns a plain int 0 (not "0.00") when no rows match, unlike
            // the "150.00"-shaped string it returns for a real decimal sum
            // — this keeps the field consistently 2-decimal either way.
            'total_revenue' => number_format((float) AuctionModel::query()
                ->where('status', AuctionStatus::CLOSED->value)
                ->whereNotNull('winner_id')
                ->sum('current_value'), 2, '.', ''),
            'live_viewers_total' => $liveViewersTotal,
            'generated_at' => Carbon::now()->format(DATE_ATOM),
        ];
    }
}
