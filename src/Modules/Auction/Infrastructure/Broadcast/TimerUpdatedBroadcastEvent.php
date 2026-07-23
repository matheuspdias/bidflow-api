<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A synchronized countdown tick — corrects client clock drift in an
 * auction's final stretch, where a second or two of drift is the
 * difference between "still open" and "just missed it". Only broadcast for
 * auctions inside config('auctions.timer.broadcast_window_seconds') of
 * ending (see AuctionTimerBroadcastCommand) — a client six hours out can
 * just compute its own countdown from ends_at.
 */
final class TimerUpdatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $auctionId,
        public readonly int $secondsRemaining,
        public readonly string $endsAt,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel("auction.{$this->auctionId}")];
    }

    public function broadcastAs(): string
    {
        return 'timer.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'seconds_remaining' => $this->secondsRemaining,
            'ends_at' => $this->endsAt,
        ];
    }
}
