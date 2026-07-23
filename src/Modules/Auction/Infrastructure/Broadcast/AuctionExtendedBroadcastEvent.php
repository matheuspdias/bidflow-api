<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Anti-sniping pushed ends_at forward — a client with its own local
 * countdown running from the previous ends_at needs this to resync
 * immediately, not wait for the next timer.updated tick.
 */
final class AuctionExtendedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $auctionId,
        public readonly string $newEndsAt,
        public readonly int $extensionsCount,
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
        return 'auction.extended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'new_ends_at' => $this->newEndsAt,
            'extensions_count' => $this->extensionsCount,
        ];
    }
}
