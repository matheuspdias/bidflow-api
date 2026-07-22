<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Auction verify facts about a seller (e.g. before scheduling
 * an auction) without depending on Modules\User internals directly.
 */
interface SellerLookup
{
    public function exists(int $sellerId): bool;

    public function isBlocked(int $sellerId): bool;
}
