<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\UseCases;

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Exceptions\AuctionNotFoundException;
use App\Modules\Auction\Domain\Exceptions\NotAuctionOwnerException;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Shared\Domain\Contracts\UserIdentity;

final class CancelAuctionUseCase
{
    public function __construct(private readonly AuctionRepository $auctions)
    {
    }

    public function execute(int $auctionId, UserIdentity $requester): Auction
    {
        $auction = $this->auctions->findById($auctionId);

        if ($auction === null) {
            throw new AuctionNotFoundException($auctionId);
        }

        if (! $auction->isOwnedBy($requester)) {
            throw new NotAuctionOwnerException();
        }

        $auction->cancel();
        $this->auctions->save($auction);

        foreach ($auction->pullDomainEvents() as $domainEvent) {
            event($domainEvent);
        }

        return $auction;
    }
}
