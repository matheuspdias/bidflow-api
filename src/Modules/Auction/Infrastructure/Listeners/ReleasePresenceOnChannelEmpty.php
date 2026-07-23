<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\UserLeftAuction;
use DateTimeImmutable;
use Illuminate\Support\Facades\Redis;
use Laravel\Reverb\Events\ChannelRemoved;

/**
 * Covers the one case TrackPresenceChannelMembership can't: when the last
 * remaining viewer of a presence-auction.{id} channel unsubscribes, Reverb's
 * PresenceChannel::unsubscribe() removes the connection *first*, and only
 * then attempts to broadcast member_removed to "everyone else" — but there
 * is no one else left, so that foreach never runs and no MessageSent frame
 * is ever sent. Reverb does still dispatch ChannelRemoved at that exact
 * moment (the channel itself just became empty), which is the only signal
 * left to react to.
 *
 * Whatever is still in our Redis set at that point (0 or 1 user — everyone
 * else already left through the normal MessageSent path, decrementing the
 * set one at a time while others were still there to receive the
 * broadcast) is the final departure this listener accounts for.
 */
final class ReleasePresenceOnChannelEmpty
{
    private const CHANNEL_PATTERN = '/^presence-auction\.(\d+)$/';

    public function handle(ChannelRemoved $event): void
    {
        if (! preg_match(self::CHANNEL_PATTERN, $event->channel->name(), $matches)) {
            return;
        }

        $auctionId = (int) $matches[1];
        $viewersKey = "auction:{$auctionId}:viewers";

        $remaining = Redis::smembers($viewersKey);

        if ($remaining === []) {
            return;
        }

        Redis::del($viewersKey);

        foreach ($remaining as $userId) {
            event(new UserLeftAuction($auctionId, (int) $userId, new DateTimeImmutable()));
        }
    }
}
