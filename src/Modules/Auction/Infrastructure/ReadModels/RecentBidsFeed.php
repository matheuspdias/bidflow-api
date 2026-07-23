<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\ReadModels;

use App\Modules\Auction\Domain\Entities\Bid;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use Illuminate\Support\Facades\Redis;

/**
 * The live snapshot endpoint's recent-bid feed. Redis (LPUSH+LTRIM, kept
 * bounded to LIMIT entries by UpdateAuctionStatsConsumer as each
 * auction.bid_placed event arrives) is the fast path — O(1) list read, no
 * query against the transactional bids table on a page every viewer hits.
 *
 * Falls back to BidRepository::recentForAuction() (the bids table itself)
 * whenever the Redis list is empty — not a rare corner case: it's the
 * correct behaviour for any auction with bids placed before this cache
 * existed, or after a Redis flush/restart, since Redis carries no
 * durability guarantee here and was never meant to be the source of truth.
 */
final class RecentBidsFeed
{
    public const LIMIT = 50;

    public function __construct(private readonly BidRepository $bids)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAuction(int $auctionId): array
    {
        $cached = Redis::lrange(self::redisKey($auctionId), 0, self::LIMIT - 1);

        if ($cached !== []) {
            return array_map(static fn (string $json) => json_decode($json, true), $cached);
        }

        return array_map(static fn (Bid $bid) => [
            'id' => $bid->id(),
            'bidder_id' => $bid->bidderId(),
            'amount' => $bid->amount()->amount(),
            'placed_at' => $bid->placedAt()->format(DATE_ATOM),
        ], $this->bids->recentForAuction($auctionId, self::LIMIT));
    }

    public static function redisKey(int $auctionId): string
    {
        return "auction:{$auctionId}:recent_bids";
    }
}
