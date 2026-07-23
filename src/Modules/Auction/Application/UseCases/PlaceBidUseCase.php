<?php

declare(strict_types=1);

namespace App\Modules\Auction\Application\UseCases;

use App\Modules\Auction\Application\DTOs\BidPlacementResult;
use App\Modules\Auction\Domain\Exceptions\AuctionClosedException;
use App\Modules\Auction\Domain\Exceptions\AuctionNotFoundException;
use App\Modules\Auction\Domain\Exceptions\BidderBlockedException;
use App\Modules\Auction\Domain\Exceptions\BidTooLowException;
use App\Modules\Auction\Domain\Exceptions\SellerCannotBidException;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidAuditLogRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Shared\Domain\Contracts\BidderLookup;
use App\Shared\Domain\ValueObjects\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The transactional/locking/idempotency/rate-limit choke point of the whole
 * system (see ADR-0006). Every rejection path must still produce an audit
 * log row, and domain events must only ever be dispatched after the
 * transaction that produced them has committed — never before, or listeners
 * (from Fase 5 onward) could act on data that a rollback later erases.
 */
final class PlaceBidUseCase
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly BidRepository $bids,
        private readonly BidAuditLogRepository $auditLogs,
        private readonly BidderLookup $bidderLookup,
    ) {
    }

    public function execute(
        int $auctionId,
        int $bidderId,
        Money $amount,
        ?string $ipAddress,
        ?string $userAgent,
    ): BidPlacementResult {
        $capturedException = null;

        $result = DB::transaction(function () use ($auctionId, $bidderId, $amount, $ipAddress, $userAgent, &$capturedException) {
            $capturedException = null;

            $auction = $this->auctions->findByIdForUpdate($auctionId);

            if ($auction === null) {
                $this->auditLogs->record($auctionId, $bidderId, $amount, $ipAddress, $userAgent, 'rejected', 'Auction not found.');
                $capturedException = new AuctionNotFoundException($auctionId);

                return null;
            }

            if ($this->bidderLookup->isBlocked($bidderId)) {
                $this->auditLogs->record($auctionId, $bidderId, $amount, $ipAddress, $userAgent, 'rejected', 'Bidder is blocked.');
                $capturedException = new BidderBlockedException();

                return null;
            }

            try {
                $isNewParticipant = ! $this->bids->hasBidderBidOn($auctionId, $bidderId);
                $bid = $auction->placeBid($bidderId, $amount, $isNewParticipant);
            } catch (AuctionClosedException|SellerCannotBidException|BidTooLowException $exception) {
                $this->auditLogs->record($auctionId, $bidderId, $amount, $ipAddress, $userAgent, 'rejected', $exception->getMessage());
                $capturedException = $exception;

                return null;
            }

            $bid = $this->bids->create($bid);
            $auction->markHighestBid($bid->id());
            $this->auctions->save($auction);

            $this->auditLogs->record($auctionId, $bidderId, $amount, $ipAddress, $userAgent, 'accepted', null);

            return new BidPlacementResult($auction, $bid);
        }, 3);

        if ($capturedException instanceof DomainException) {
            throw $capturedException;
        }

        foreach ($result->auction->pullDomainEvents() as $domainEvent) {
            event($domainEvent);
        }

        return $result;
    }
}
