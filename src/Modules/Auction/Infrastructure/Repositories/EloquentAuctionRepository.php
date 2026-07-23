<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Repositories;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Repositories\AuctionPage;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use App\Modules\Auction\Infrastructure\Persistence\Models\Auction as AuctionModel;
use App\Shared\Domain\ValueObjects\DateRange;
use App\Shared\Domain\ValueObjects\Money;

final class EloquentAuctionRepository implements AuctionRepository
{
    public function findById(int $id): ?Auction
    {
        $model = AuctionModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdForUpdate(int $id): ?Auction
    {
        $model = AuctionModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function paginate(int $page, int $perPage, ?AuctionStatus $status = null, ?int $categoryId = null): AuctionPage
    {
        $query = AuctionModel::query()->latest('id');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return new AuctionPage(
            items: array_map($this->toDomain(...), $paginator->items()),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    public function create(Auction $auction): Auction
    {
        $model = AuctionModel::create($this->toAttributes($auction));

        $auction->assignId($model->id);

        return $auction;
    }

    public function save(Auction $auction): void
    {
        AuctionModel::whereKey($auction->id())->update($this->toAttributes($auction));
    }

    /**
     * @return array<string, mixed>
     */
    private function toAttributes(Auction $auction): array
    {
        return [
            'seller_id' => $auction->sellerId(),
            'category_id' => $auction->categoryId(),
            'name' => $auction->name(),
            'description' => $auction->description(),
            'starting_bid' => $auction->startingBid()->amount(),
            'minimum_increment' => $auction->minimumIncrement()->amount(),
            'buy_now_price' => $auction->buyNowPrice()?->amount(),
            'reserve_price' => $auction->reservePrice()?->amount(),
            'status' => $auction->status()->value,
            'starts_at' => $auction->dateRange()->start,
            'ends_at' => $auction->dateRange()->end,
            'current_value' => $auction->currentValue()->amount(),
            'participant_count' => $auction->participantCount(),
            'view_count' => $auction->viewCount(),
        ];
    }

    private function toDomain(AuctionModel $model): Auction
    {
        $currency = config('money.default_currency');

        return Auction::reconstitute(
            id: $model->id,
            sellerId: $model->seller_id,
            categoryId: $model->category_id,
            name: $model->name,
            description: $model->description,
            startingBid: Money::of($model->starting_bid, $currency),
            minimumIncrement: Money::of($model->minimum_increment, $currency),
            buyNowPrice: $model->buy_now_price !== null ? Money::of($model->buy_now_price, $currency) : null,
            reservePrice: $model->reserve_price !== null ? Money::of($model->reserve_price, $currency) : null,
            status: AuctionStatus::from($model->status),
            schedule: DateRange::of($model->starts_at->toDateTimeImmutable(), $model->ends_at->toDateTimeImmutable()),
            currentValue: Money::of($model->current_value, $currency),
            participantCount: $model->participant_count,
            viewCount: $model->view_count,
        );
    }
}
