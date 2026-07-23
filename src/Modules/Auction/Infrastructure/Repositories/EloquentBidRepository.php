<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Repositories;

use App\Modules\Auction\Domain\Entities\Bid;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid as BidModel;
use App\Shared\Domain\ValueObjects\Money;

final class EloquentBidRepository implements BidRepository
{
    public function create(Bid $bid): Bid
    {
        $model = BidModel::create([
            'auction_id' => $bid->auctionId(),
            'bidder_id' => $bid->bidderId(),
            'amount' => $bid->amount()->amount(),
            'status' => 'accepted',
            'created_at' => $bid->placedAt(),
        ]);

        $bid->assignId($model->id);

        return $bid;
    }

    public function findById(int $id): ?Bid
    {
        $model = BidModel::find($id);

        if ($model === null) {
            return null;
        }

        return Bid::reconstitute(
            id: $model->id,
            auctionId: $model->auction_id,
            bidderId: $model->bidder_id,
            amount: Money::of($model->amount, config('money.default_currency')),
            placedAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    public function hasBidderBidOn(int $auctionId, int $bidderId): bool
    {
        return BidModel::query()
            ->where('auction_id', $auctionId)
            ->where('bidder_id', $bidderId)
            ->exists();
    }

    public function recentForAuction(int $auctionId, int $limit = 50): array
    {
        $currency = config('money.default_currency');

        return BidModel::query()
            ->where('auction_id', $auctionId)
            // created_at alone isn't a reliable tiebreaker — Postgres
            // timestamp columns default to 0 fractional-second precision
            // (Laravel's timestamp() migration helper), so two bids landing
            // in the same second (very possible with real users bidding in
            // quick succession) would sort arbitrarily without id as a
            // secondary key. id is monotonically increasing with insertion
            // order, which created_at alone doesn't guarantee to expose.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BidModel $model) => Bid::reconstitute(
                id: $model->id,
                auctionId: $model->auction_id,
                bidderId: $model->bidder_id,
                amount: Money::of($model->amount, $currency),
                placedAt: $model->created_at->toDateTimeImmutable(),
            ))
            ->all();
    }

    public function afterId(int $auctionId, int $afterId, int $limit = 200): array
    {
        $currency = config('money.default_currency');

        return BidModel::query()
            ->where('auction_id', $auctionId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (BidModel $model) => Bid::reconstitute(
                id: $model->id,
                auctionId: $model->auction_id,
                bidderId: $model->bidder_id,
                amount: Money::of($model->amount, $currency),
                placedAt: $model->created_at->toDateTimeImmutable(),
            ))
            ->all();
    }
}
