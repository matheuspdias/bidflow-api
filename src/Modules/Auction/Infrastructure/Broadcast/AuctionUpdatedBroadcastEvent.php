<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A resync of summarized auction state — status/current_value/participant
 * count — as opposed to BidPlacedBroadcastEvent's feed entry. A client that
 * missed a beat (Fase 13: reconnection) can trust this to bring its header
 * back in sync without replaying the whole feed.
 */
final class AuctionUpdatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $auctionId,
        public readonly string $status,
        public readonly string $currentValue,
        public readonly int $participantCount,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("auction.{$this->auctionId}")];
    }

    public function broadcastAs(): string
    {
        return 'auction.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'status' => $this->status,
            'current_value' => $this->currentValue,
            'participant_count' => $this->participantCount,
        ];
    }
}
