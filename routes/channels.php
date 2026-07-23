<?php

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Any authenticated user can watch any auction's live updates — this is a
 * public auction house, not a private negotiation. The Sanctum guard on
 * /broadcasting/auth already enforces "a real, logged-in user" before this
 * callback ever runs (see ADR-0011); returning an array (rather than true)
 * is what makes this a presence channel — Laravel infers presence vs.
 * private from the requested channel's prefix, not from a separate route,
 * so the same handler that used to return a bool for private-auction.{id}
 * now classifies a role for presence-auction.{id} (Fase 8, ADR-0012).
 *
 * "seller"/"bidder" reflect this specific auction, not a site-wide role —
 * the seller of auction A is just a "viewer" on auction B's channel.
 */
Broadcast::channel('auction.{auctionId}', function ($user, int $auctionId) {
    $auction = app(AuctionRepository::class)->findById($auctionId);

    if ($auction === null) {
        return false;
    }

    $role = match (true) {
        $auction->sellerId() === (int) $user->id => 'seller',
        app(BidRepository::class)->hasBidderBidOn($auctionId, (int) $user->id) => 'bidder',
        default => 'viewer',
    };

    return ['id' => (int) $user->id, 'role' => $role];
});

/**
 * Business dashboard metrics (Fase 14) — a plain private channel, not
 * presence: nobody needs to know who else is watching, only the numbers.
 * Any authenticated user may subscribe — this system has no differentiated
 * admin role yet (see ADR-0018 for why that's an accepted simplification,
 * not an oversight).
 */
Broadcast::channel('dashboard', function ($user) {
    return true;
});
