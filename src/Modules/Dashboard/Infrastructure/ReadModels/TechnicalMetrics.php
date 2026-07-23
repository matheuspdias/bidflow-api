<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\ReadModels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/**
 * Unlike the business dashboard (Fase 14), this needs no
 * Shared\Domain\Contracts\* lookup into another module — every source here
 * (Redis counters written by RabbitMqConsumerCommand::recordMetrics(), the
 * RabbitMQ management HTTP API, Reverb's own Pusher-compatible HTTP API)
 * is shared infrastructure, not another module's business data. See
 * ADR-0019.
 */
final class TechnicalMetrics
{
    /**
     * The consumerName()/queue pair for every RabbitMQ consumer in the
     * system. No registry to derive this from without instantiating every
     * consumer class — a short hardcoded list here is simpler than that,
     * and short enough that keeping it in sync by hand is not a burden.
     *
     * @var list<array{name: string, queue: string}>
     */
    private const CONSUMERS = [
        ['name' => 'update_auction_stats', 'queue' => 'domain_events.update_auction_stats'],
        ['name' => 'persist_bid_history', 'queue' => 'domain_events.persist_bid_history'],
        ['name' => 'send_bid_notification', 'queue' => 'domain_events.send_bid_notification'],
        ['name' => 'broadcast_bid', 'queue' => 'domain_events.broadcast_bid'],
        ['name' => 'broadcast_viewer_count', 'queue' => 'domain_events.broadcast_viewer_count'],
        ['name' => 'broadcast_auction_extended', 'queue' => 'domain_events.broadcast_auction_extended'],
        ['name' => 'broadcast_auction_ended', 'queue' => 'domain_events.broadcast_auction_ended'],
        ['name' => 'send_won_notification', 'queue' => 'domain_events.send_won_notification'],
    ];

    public function __construct(private readonly WebSocketConnectionsCounter $connectionsCounter)
    {
    }

    /**
     * @return array{
     *     websocket: array{connections: int},
     *     consumers: list<array<string, mixed>>,
     *     generated_at: string,
     * }
     */
    public function current(): array
    {
        return [
            'websocket' => ['connections' => $this->connectionsCounter->count()],
            'consumers' => array_map($this->consumerMetrics(...), self::CONSUMERS),
            'generated_at' => now()->format(DATE_ATOM),
        ];
    }

    /**
     * @param  array{name: string, queue: string}  $consumer
     * @return array<string, mixed>
     */
    private function consumerMetrics(array $consumer): array
    {
        $queueStats = $this->queueDepth($consumer['queue']);
        $processedCount = (int) (Redis::get("metrics:consumer:{$consumer['name']}:processed_count") ?? 0);
        $totalLatencyMs = (int) (Redis::get("metrics:consumer:{$consumer['name']}:total_latency_ms") ?? 0);

        return [
            'name' => $consumer['name'],
            'queue' => $consumer['queue'],
            'messages_ready' => $queueStats['messages_ready'],
            'messages_unacknowledged' => $queueStats['messages_unacknowledged'],
            'active_consumers' => $queueStats['consumers'],
            'processed_total' => $processedCount,
            'avg_latency_ms' => $processedCount > 0 ? (int) round($totalLatencyMs / $processedCount) : 0,
        ];
    }

    /**
     * @return array{messages_ready: int, messages_unacknowledged: int, consumers: int}
     */
    private function queueDepth(string $queue): array
    {
        $vhost = rawurlencode((string) config('rabbitmq.vhost'));
        $host = config('rabbitmq.host');
        $port = config('rabbitmq.management_port');
        $user = config('rabbitmq.user');
        $password = config('rabbitmq.password');

        $response = Http::withBasicAuth($user, $password)
            ->get("http://{$host}:{$port}/api/queues/{$vhost}/{$queue}");

        if (! $response->successful()) {
            // The queue may not have been declared yet (a consumer that has
            // never run) — a missing queue is "zero of everything", not an
            // error worth surfacing on a dashboard.
            return ['messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 0];
        }

        return [
            'messages_ready' => (int) ($response->json('messages_ready') ?? 0),
            'messages_unacknowledged' => (int) ($response->json('messages_unacknowledged') ?? 0),
            'consumers' => (int) ($response->json('consumers') ?? 0),
        ];
    }
}
