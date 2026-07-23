<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Adapters;

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction as AuctionModel;
use App\Shared\Domain\Contracts\BuyerRankingLookup;
use Illuminate\Support\Facades\DB;

/**
 * The simplest defensible buyer ranking: most auctions won, closed
 * auctions only. Not "most bids placed" or "most spent" — those reward
 * activity or budget, not actually winning, which is the number a buyer
 * cares about seeing themselves ranked on.
 */
final class BuyerRankingLookupAdapter implements BuyerRankingLookup
{
    public function topWinners(int $limit): array
    {
        return AuctionModel::query()
            ->whereNotNull('winner_id')
            ->where('status', 'closed')
            ->groupBy('winner_id')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit($limit)
            ->get(['winner_id', DB::raw('count(*) as wins')])
            // wins is a raw aggregate alias, not a real column — read via
            // getAttribute() so PHPStan/Larastan doesn't expect it declared.
            ->map(static fn ($row): array => [
                'user_id' => (int) $row->winner_id,
                'wins' => (int) $row->getAttribute('wins'),
            ])
            ->all();
    }
}
