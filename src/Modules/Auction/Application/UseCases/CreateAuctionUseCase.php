<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\UseCases;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Shared\Domain\ValueObjects\DateRange;
use App\Shared\Domain\ValueObjects\Money;

final class CreateAuctionUseCase
{
    public function __construct(private readonly AuctionRepository $auctions)
    {
    }

    public function execute(
        int $sellerId,
        int $categoryId,
        string $name,
        string $description,
        Money $startingBid,
        Money $minimumIncrement,
        ?Money $buyNowPrice,
        ?Money $reservePrice,
        DateRange $schedule,
    ): Auction {
        $auction = Auction::schedule(
            sellerId: $sellerId,
            categoryId: $categoryId,
            name: $name,
            description: $description,
            startingBid: $startingBid,
            minimumIncrement: $minimumIncrement,
            buyNowPrice: $buyNowPrice,
            reservePrice: $reservePrice,
            schedule: $schedule,
        );

        return $this->auctions->create($auction);
    }
}
