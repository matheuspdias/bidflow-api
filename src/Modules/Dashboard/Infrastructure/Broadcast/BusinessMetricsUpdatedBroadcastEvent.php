<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A plain private channel, not presence — nobody needs to know who else is
 * watching the business dashboard, only the metrics themselves. Any
 * authenticated user can subscribe (see routes/channels.php); this system
 * has no differentiated admin role yet, the same pragmatic simplification
 * "auction.{id}" made for viewers back in Fase 7/8 (ADR-0018).
 */
final class BusinessMetricsUpdatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(public readonly array $metrics)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->metrics;
    }
}
