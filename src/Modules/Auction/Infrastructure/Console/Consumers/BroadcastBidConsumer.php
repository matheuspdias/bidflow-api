<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use Illuminate\Support\Facades\Log;

/**
 * Body filled in Fase 7 (broadcast bid.placed over Reverb). Wired up now —
 * consuming, idempotent, ack'ing — so Fase 7 only has to fill in process(),
 * not build the consumer itself.
 */
final class BroadcastBidConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:bid-broadcast {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.bid_placed events to broadcast bid.placed over WebSocket (stub — Fase 7)';

    protected function consumerName(): string
    {
        return 'broadcast_bid';
    }

    protected function routingKey(): string
    {
        return 'auction.bid_placed';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function process(array $payload): void
    {
        // @todo Fase 7: broadcast BidPlacedBroadcastEvent over Reverb.
        Log::debug('BroadcastBidConsumer received a BidPlaced event (stub).', [
            'auction_id' => $payload['auction_id'] ?? null,
        ]);
    }
}
