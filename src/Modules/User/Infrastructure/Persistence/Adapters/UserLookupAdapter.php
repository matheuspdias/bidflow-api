<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Adapters;

use App\Modules\User\Domain\Repositories\UserRepository;
use App\Shared\Domain\Contracts\BidderLookup;
use App\Shared\Domain\Contracts\SellerLookup;

/**
 * A seller and a bidder are both "just a user" from Modules\User's point of
 * view, so a single adapter satisfies both Shared\Domain contracts instead
 * of duplicating the same two methods across two classes.
 */
final class UserLookupAdapter implements SellerLookup, BidderLookup
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function exists(int $sellerId): bool
    {
        return $this->users->existsById($sellerId);
    }

    public function isBlocked(int $sellerId): bool
    {
        return $this->users->findById($sellerId)?->isBlocked() ?? false;
    }
}
