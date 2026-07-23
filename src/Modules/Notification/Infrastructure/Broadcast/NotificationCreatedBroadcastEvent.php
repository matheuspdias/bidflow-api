<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast straight from NotificationDispatcherAdapter rather than through
 * the RabbitMQ integration-event pipeline every Auction-side broadcast
 * uses: dispatch() already runs from inside a RabbitMQ consumer process
 * (SendBidNotificationConsumer/SendWonNotificationConsumer) — routing
 * through the broker a second time would just add latency, the same
 * reasoning as BidPlacedBroadcastEvent using ShouldBroadcastNow (ADR-0011).
 *
 * "App.Models.User.{id}" matches the private channel already registered in
 * routes/channels.php (Fase 2) — a raw string, not a reference to
 * Modules\User's Eloquent model, so this stays free of a cross-module
 * dependency.
 */
final class NotificationCreatedBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $notificationId,
        public readonly string $type,
        public readonly array $data,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notificationId,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
