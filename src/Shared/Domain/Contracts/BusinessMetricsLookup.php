<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Dashboard answer "how's the business doing right now"
 * without depending on Modules\Auction internals directly.
 */
interface BusinessMetricsLookup
{
    /**
     * @return array{
     *     auctions: array{scheduled: int, active: int, closed: int, cancelled: int},
     *     total_bids: int,
     *     total_revenue: string,
     *     live_viewers_total: int,
     *     generated_at: string,
     * }
     */
    public function current(): array;
}
