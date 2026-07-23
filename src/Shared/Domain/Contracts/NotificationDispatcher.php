<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Auction raise a notification (outbid, won, ...) without
 * depending on Modules\Notification internals directly.
 */
interface NotificationDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(int $userId, string $type, array $data): void;
}
