<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A feed entry: "this bid landed". Deliberately decoupled from
 * AuctionUpdatedBroadcastEvent (resync of summarized auction state) — a
 * client appending to a live feed and a client resyncing its header/current
 * price are two different concerns with two different payload shapes.
 *
 * ShouldBroadcastNow (not ShouldBroadcast): this is already dispatched from
 * a background consumer process, not a web request — queuing it again
 * would just add latency for no benefit.
 */
final class BidPlacedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int $auctionId,
        public readonly int $bidId,
        public readonly int $bidderId,
        public readonly string $amount,
        public readonly string $placedAt,
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
        return 'bid.placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->bidId,
            'auction_id' => $this->auctionId,
            'bidder_id' => $this->bidderId,
            'amount' => $this->amount,
            'placed_at' => $this->placedAt,
        ];
    }
}
