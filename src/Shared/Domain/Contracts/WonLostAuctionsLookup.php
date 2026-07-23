<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\User answer "auctions I've won/lost" without depending on
 * Modules\Auction internals directly.
 */
interface WonLostAuctionsLookup
{
    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, per_page: int, current_page: int}}
     */
    public function paginateWon(int $userId, int $page, int $perPage): array;

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, per_page: int, current_page: int}}
     */
    public function paginateLost(int $userId, int $page, int $perPage): array;
}
