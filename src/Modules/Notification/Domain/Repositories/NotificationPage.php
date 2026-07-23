<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Repositories;

use App\Modules\Notification\Domain\Aggregates\Notification;

final class NotificationPage
{
    /**
     * @param  list<Notification>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $currentPage,
    ) {
    }
}
