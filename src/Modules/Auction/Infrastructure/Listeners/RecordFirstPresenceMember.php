<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\UserJoinedAuction;
use DateTimeImmutable;
use Illuminate\Support\Facades\Redis;
use Laravel\Reverb\Events\ChannelCreated;
use React\EventLoop\Loop;

/**
 * Complements TrackPresenceChannelMembership for the symmetric edge case it
 * structurally can't see: the very *first* subscriber's own join. Reverb's
 * InteractsWithPresenceChannels::subscribe() broadcasts member_added to
 * "every other connection on the channel" — for the first subscriber there
 * are none, so no MessageSent frame is ever sent and that listener never
 * fires for them (they'd otherwise be permanently undercounted by one,
 * i.e. every single session, not a rare corner case).
 *
 * ChannelCreated fires exactly at that 0-to-1 transition — but at the
 * moment it's dispatched, EventHandler::subscribe() (see
 * vendor/laravel/reverb/src/Protocols/Pusher/EventHandler.php) has only
 * just called findOrCreate() and is about to call $channel->subscribe(...)
 * next in the same synchronous call — the channel is still empty when this
 * listener runs. Loop::futureTick() defers the read to the next tick of
 * Reverb's own ReactPHP event loop, by which point that subscribe() call
 * has completed and the connection is really there.
 */
final class RecordFirstPresenceMember
{
    private const CHANNEL_PATTERN = '/^presence-auction\.(\d+)$/';

    public function handle(ChannelCreated $event): void
    {
        if (! preg_match(self::CHANNEL_PATTERN, $event->channel->name(), $matches)) {
            return;
        }

        $auctionId = (int) $matches[1];
        $channel = $event->channel;

        Loop::futureTick(function () use ($channel, $auctionId): void {
            foreach ($channel->connections() as $connection) {
                $userId = $connection->data('user_id');

                if ($userId === null) {
                    continue;
                }

                $userId = (int) $userId;

                if (Redis::sadd("auction:{$auctionId}:viewers", $userId) === 1) {
                    event(new UserJoinedAuction($auctionId, $userId, new DateTimeImmutable()));
                }
            }
        });
    }
}
