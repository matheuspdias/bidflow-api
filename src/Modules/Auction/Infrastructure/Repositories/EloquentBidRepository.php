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
            ->latest('created_at')
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
