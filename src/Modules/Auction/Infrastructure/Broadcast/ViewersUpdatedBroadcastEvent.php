<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live viewer count for an auction — deliberately separate from
 * AuctionUpdatedBroadcastEvent's participant_count, which counts unique
 * *bidders* (Auction::placeBid()'s isNewParticipant), not people currently
 * watching. A viewer who never bids still counts here and never there.
 */
final class ViewersUpdatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $auctionId,
        public readonly int $viewerCount,
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
        return 'viewers.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'viewer_count' => $this->viewerCount,
        ];
    }
}
