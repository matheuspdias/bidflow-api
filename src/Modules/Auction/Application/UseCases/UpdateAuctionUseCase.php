<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\UseCases;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Exceptions\AuctionNotFoundException;
use App\Modules\Auction\Domain\Exceptions\NotAuctionOwnerException;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Shared\Domain\Contracts\UserIdentity;
use App\Shared\Domain\ValueObjects\DateRange;

final class UpdateAuctionUseCase
{
    public function __construct(private readonly AuctionRepository $auctions)
    {
    }

    public function execute(
        int $auctionId,
        UserIdentity $requester,
        string $name,
        string $description,
        int $categoryId,
        DateRange $schedule,
    ): Auction {
        $auction = $this->auctions->findById($auctionId);

        if ($auction === null) {
            throw new AuctionNotFoundException($auctionId);
        }

        if (! $auction->isOwnedBy($requester)) {
            throw new NotAuctionOwnerException();
        }

        $auction->updateDetails($name, $description, $categoryId, $schedule);
        $this->auctions->save($auction);

        return $auction;
    }
}
