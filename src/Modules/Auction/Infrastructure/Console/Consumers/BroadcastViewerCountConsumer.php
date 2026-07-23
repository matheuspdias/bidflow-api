<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Modules\Auction\Infrastructure\Broadcast\ViewersUpdatedBroadcastEvent;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use Illuminate\Support\Facades\Redis;

/**
 * Reacts to both auction.user_joined and auction.user_left on the same
 * queue (see RabbitMqConsumerCommand::additionalRoutingKeys()) — either one
 * means the viewer count for that auction may have changed, and the
 * response is identical: re-read the count and broadcast it.
 *
 * The count itself is never carried in the event payload — it's read fresh
 * from Redis::scard() at broadcast time, since TrackPresenceChannelMembership
 * /ReleasePresenceOnChannelEmpty (running inside the Reverb process, not
 * here) are the source of truth for the set itself. This also means a
 * duplicate/replayed join or leave event just re-broadcasts the same
 * already-correct count — harmless.
 */
final class BroadcastViewerCountConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:viewer-count {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.user_joined / auction.user_left events and broadcast the live viewer count';

    protected function consumerName(): string
    {
        return 'broadcast_viewer_count';
    }

    protected function routingKey(): string
    {
        return 'auction.user_joined';
    }

    /**
     * @return list<string>
     */
    protected function additionalRoutingKeys(): array
    {
        return ['auction.user_left'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function process(array $payload): void
    {
        $auctionId = (int) $payload['auction_id'];
        $viewerCount = (int) Redis::scard("auction:{$auctionId}:viewers");

        broadcast(new ViewersUpdatedBroadcastEvent($auctionId, $viewerCount));
    }
}
