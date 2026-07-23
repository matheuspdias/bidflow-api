<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\UseCases;

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Called once per ended auction by AuctionClosingCommand. Uses the same
 * lock-then-check pattern as PlaceBidUseCase (ADR-0006): findByIdForUpdate()
 * inside a transaction, re-checking status after acquiring the lock, since
 * the id came from a plain (unlocked) query moments earlier and another
 * tick — or a bid landing in the anti-sniping window right before this
 * runs — could have changed things in the meantime.
 */
final class CloseAuctionUseCase
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly BidRepository $bids,
    ) {
    }

    public function execute(int $auctionId): void
    {
        $auction = DB::transaction(function () use ($auctionId) {
            $auction = $this->auctions->findByIdForUpdate($auctionId);

            if ($auction === null || $auction->status() !== AuctionStatus::ACTIVE) {
                return null;
            }

            if ($auction->dateRange()->end > new DateTimeImmutable()) {
                // The anti-sniping window pushed ends_at forward after this
                // auction's id was selected by the polling query — no
                // longer actually ended.
                return null;
            }

            $winningBid = $auction->highestBidId() !== null
                ? $this->bids->findById($auction->highestBidId())
                : null;

            $auction->close($winningBid?->bidderId(), $winningBid?->amount());
            $this->auctions->save($auction);

            return $auction;
        }, 3);

        if ($auction === null) {
            return;
        }

        foreach ($auction->pullDomainEvents() as $domainEvent) {
            event($domainEvent);
        }
    }
}
