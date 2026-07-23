<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Listeners;

use App\Modules\Auction\Domain\Events\UserJoinedAuction;
use App\Modules\Auction\Domain\Events\UserLeftAuction;
use DateTimeImmutable;
use Illuminate\Support\Facades\Redis;
use Laravel\Reverb\Events\MessageSent;

/**
 * Reverb has no outbound webhook for presence join/leave (ADR-0012) — this
 * listener is the substitute. Presence membership changes are broadcast by
 * Reverb as internal Pusher-protocol frames (pusher_internal:member_added /
 * member_removed) sent straight to already-subscribed connections; every
 * outbound frame — these included — passes through Connection::send(),
 * which fires this exact MessageSent event. Running inside the Reverb
 * process itself (registered the same way for every artisan command via
 * AuctionServiceProvider::boot()), this is the only place the backend can
 * observe presence transitions server-side.
 *
 * MessageSent fires once per *recipient* connection a broadcast fans out
 * to, so a single member_added is seen here N times (N = other viewers
 * already on the channel) with identical content. Redis::sadd()'s return
 * value — 1 only the first time a member is actually added — is what makes
 * this idempotent without extra bookkeeping.
 *
 * The mirror-image gap (the *last* viewer leaving) is handled separately by
 * ReleasePresenceOnChannelEmpty, since member_removed's own broadcast has no
 * remaining connections to fan out to and never reaches this listener.
 */
final class TrackPresenceChannelMembership
{
    private const CHANNEL_PATTERN = '/^presence-auction\.(\d+)$/';

    public function handle(MessageSent $event): void
    {
        $payload = json_decode($event->message, true);

        if (! is_array($payload)) {
            return;
        }

        $channelEvent = $payload['event'] ?? null;

        if (! in_array($channelEvent, ['pusher_internal:member_added', 'pusher_internal:member_removed'], true)) {
            return;
        }

        if (! preg_match(self::CHANNEL_PATTERN, (string) ($payload['channel'] ?? ''), $matches)) {
            return;
        }

        $auctionId = (int) $matches[1];

        /** @var array<string, mixed> $memberData */
        $memberData = json_decode((string) ($payload['data'] ?? '{}'), true) ?? [];
        $userId = $memberData['user_id'] ?? null;

        if ($userId === null) {
            return;
        }

        $userId = (int) $userId;
        $viewersKey = "auction:{$auctionId}:viewers";

        if ($channelEvent === 'pusher_internal:member_added') {
            if (Redis::sadd($viewersKey, $userId) === 1) {
                event(new UserJoinedAuction($auctionId, $userId, new DateTimeImmutable()));
            }

            return;
        }

        if (Redis::srem($viewersKey, $userId) === 1) {
            event(new UserLeftAuction($auctionId, $userId, new DateTimeImmutable()));
        }
    }
}
