<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Adapters;

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction as AuctionModel;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid as BidModel;
use App\Shared\Domain\Contracts\WonLostAuctionsLookup;
use Illuminate\Database\Eloquent\Builder;

final class WonLostAuctionsLookupAdapter implements WonLostAuctionsLookup
{
    public function paginateWon(int $userId, int $page, int $perPage): array
    {
        $paginator = AuctionModel::query()
            ->where('status', 'closed')
            ->where('winner_id', $userId)
            ->latest('ends_at')
            ->paginate(perPage: $perPage, page: $page);

        return $this->toPage($paginator);
    }

    public function paginateLost(int $userId, int $page, int $perPage): array
    {
        $paginator = AuctionModel::query()
            ->where('status', 'closed')
            ->where(function (Builder $query) use ($userId) {
                $query->whereNull('winner_id')->orWhere('winner_id', '!=', $userId);
            })
            ->whereExists(function ($query) use ($userId) {
                $query->select('id')
                    ->from((new BidModel())->getTable())
                    ->whereColumn('bids.auction_id', 'auctions.id')
                    ->where('bids.bidder_id', $userId);
            })
            ->latest('ends_at')
            ->paginate(perPage: $perPage, page: $page);

        return $this->toPage($paginator);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AuctionModel>  $paginator
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, per_page: int, current_page: int}}
     */
    private function toPage($paginator): array
    {
        return [
            'data' => array_map(static fn (AuctionModel $auction): array => [
                'id' => $auction->id,
                'name' => $auction->name,
                'final_price' => (string) $auction->current_value,
                'winner_id' => $auction->winner_id,
                'ends_at' => $auction->ends_at->format(DATE_ATOM),
            ], $paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ],
        ];
    }
}
