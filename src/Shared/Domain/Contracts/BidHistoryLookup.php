<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\User answer "my bid history" without depending on
 * Modules\Auction internals directly. Returns plain arrays rather than a
 * shared DTO class — the shape below is the contract, not an Auction
 * domain type leaking across the module boundary.
 */
interface BidHistoryLookup
{
    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, per_page: int, current_page: int}}
     */
    public function paginateForBidder(int $bidderId, int $page, int $perPage): array;
}
