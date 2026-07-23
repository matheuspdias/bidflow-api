<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anti-sniping
    |--------------------------------------------------------------------------
    |
    | A bid landing inside window_seconds of ends_at pushes ends_at forward
    | by extension_seconds — up to max_extensions times per auction, so a
    | determined bidder can't prolong an auction indefinitely by bidding in
    | the last second of every extension. See ADR-0014.
    |
    */

    'anti_sniping' => [
        'window_seconds' => (int) env('ANTI_SNIPING_WINDOW_SECONDS', 120),
        'extension_seconds' => (int) env('ANTI_SNIPING_EXTENSION_SECONDS', 120),
        'max_extensions' => (int) env('ANTI_SNIPING_MAX_EXTENSIONS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronized timer
    |--------------------------------------------------------------------------
    |
    | consume:auction-timer only broadcasts timer.updated for active
    | auctions ending within this many seconds — synchronization matters in
    | the final stretch, not for an auction with six hours left, where a
    | client's own clock against ends_at (already in AuctionResource) is
    | close enough.
    |
    */

    'timer' => [
        'broadcast_window_seconds' => (int) env('TIMER_BROADCAST_WINDOW_SECONDS', 300),
    ],

];
