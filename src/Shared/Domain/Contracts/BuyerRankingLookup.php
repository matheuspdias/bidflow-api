<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\User answer "top buyers" without depending on
 * Modules\Auction internals directly.
 */
interface BuyerRankingLookup
{
    /**
     * @return list<array{user_id: int, wins: int}>
     */
    public function topWinners(int $limit): array;
}
