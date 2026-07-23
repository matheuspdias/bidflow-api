<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console\Consumers;

use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use Illuminate\Support\Facades\Log;

/**
 * Body filled in Fase 11 (notify the previous highest bidder they've been
 * outbid). Wired up now — consuming, idempotent, ack'ing — so Fase 11 only
 * has to fill in process(), not build the consumer itself.
 */
final class SendBidNotificationConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'consume:bid-notifications {--limit=0 : stop after processing this many messages} {--timeout=0 : stop after this many idle seconds}';

    protected $description = 'Consume auction.bid_placed events to notify the outbid bidder (stub — Fase 11)';

    protected function consumerName(): string
    {
        return 'send_bid_notification';
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
        // @todo Fase 11: notify the previous highest bidder they've been outbid.
        Log::debug('SendBidNotificationConsumer received a BidPlaced event (stub).', [
            'auction_id' => $payload['auction_id'] ?? null,
        ]);
    }
}
