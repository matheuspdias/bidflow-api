<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Any authenticated user can watch any auction's live updates — this is a
 * public auction house, not a private negotiation. Role classification
 * (seller/bidder/viewer) for the presence channel comes in Fase 8; this
 * private channel only needs to confirm "a real, logged-in user", which the
 * Sanctum guard on /broadcasting/auth already enforces before this callback
 * ever runs (see ADR-0011).
 */
Broadcast::channel('auction.{auctionId}', function ($user, int $auctionId) {
    return true;
});
