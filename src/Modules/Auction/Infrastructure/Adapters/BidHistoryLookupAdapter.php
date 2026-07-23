<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Adapters;

use App\Modules\Auction\Infrastructure\Persistence\Models\Bid as BidModel;
use App\Shared\Domain\Contracts\BidHistoryLookup;
use Illuminate\Support\Carbon;

final class BidHistoryLookupAdapter implements BidHistoryLookup
{
    public function paginateForBidder(int $bidderId, int $page, int $perPage): array
    {
        $paginator = BidModel::query()
            ->join('auctions', 'auctions.id', '=', 'bids.auction_id')
            ->where('bids.bidder_id', $bidderId)
            // See EloquentBidRepository::recentForAuction() — created_at
            // alone can tie (Postgres timestamp() defaults to whole-second
            // precision), id is the tiebreaker.
            ->orderByDesc('bids.created_at')
            ->orderByDesc('bids.id')
            ->select([
                'bids.id',
                'bids.auction_id',
                'auctions.name as auction_name',
                'bids.amount',
                'bids.created_at as placed_at',
            ])
            ->paginate(perPage: $perPage, page: $page);

        return [
            // auction_name/placed_at are raw aliased columns, not real
            // attributes of the Bid model — getAttribute() reads them
            // without PHPStan/Larastan expecting a declared property.
            'data' => array_map(static fn (BidModel $bid): array => [
                'id' => $bid->id,
                'auction_id' => $bid->auction_id,
                'auction_name' => $bid->getAttribute('auction_name'),
                'amount' => (string) $bid->amount,
                'placed_at' => Carbon::parse($bid->getAttribute('placed_at'))->format(DATE_ATOM),
            ], $paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ],
        ];
    }
}
