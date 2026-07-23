<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Separate channel from the business dashboard's — a different audience in
 * principle (ops/engineering vs. business), even though this system has no
 * differentiated roles yet to actually enforce that split (same accepted
 * simplification as private-dashboard, ADR-0018/0019).
 */
final class TechnicalMetricsUpdatedBroadcastEvent implements ShouldBroadcastNow
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
        return [new PrivateChannel('dashboard-technical')];
    }

    public function broadcastAs(): string
    {
        return 'technical.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->metrics;
    }
}
