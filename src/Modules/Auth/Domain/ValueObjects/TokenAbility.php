<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObjects;

/**
 * The fixed vocabulary of Sanctum token abilities this API grants. Deciding
 * this now avoids re-issuing tokens later when a scoped-down token (e.g. a
 * bidding-only integration) becomes necessary.
 */
enum TokenAbility: string
{
    case BID_PLACE = 'bid:place';
    case PROFILE_READ = 'profile:read';
    case PROFILE_WRITE = 'profile:write';
    case AUCTION_MANAGE = 'auction:manage';
    case NOTIFICATIONS_READ = 'notifications:read';
    case DASHBOARD_READ = 'dashboard:read';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(static fn (self $ability): string => $ability->value, self::cases());
    }
}
