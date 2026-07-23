<?php

use App\Modules\Dashboard\Infrastructure\ReadModels\WebSocketConnectionsCounter;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;

/**
 * WebSocketConnectionsCounter is swapped for a fake here rather than faked
 * via Http::fake() — the real implementation goes through pusher-php-
 * server's own Guzzle client, which Laravel's HTTP client fake has no
 * visibility into (see the class's own docblock). The RabbitMQ management
 * API call, by contrast, goes through Illuminate\Http\Client, so Http::fake()
 * covers it directly.
 */
function fakeConnectionsCounter(int $count): void
{
    app()->instance(WebSocketConnectionsCounter::class, new class($count) extends WebSocketConnectionsCounter
    {
        public function __construct(private readonly int $fakeCount)
        {
        }

        public function count(): int
        {
            return $this->fakeCount;
        }
    });
}

test('the technical dashboard reports websocket connections and per-consumer queue/throughput metrics', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    fakeConnectionsCounter(7);

    Http::fake([
        '*/api/queues/*/domain_events.update_auction_stats' => Http::response([
            'messages_ready' => 3,
            'messages_unacknowledged' => 1,
            'consumers' => 1,
        ]),
        '*/api/queues/*' => Http::response([
            'messages_ready' => 0,
            'messages_unacknowledged' => 0,
            'consumers' => 0,
        ]),
    ]);

    Redis::set('metrics:consumer:update_auction_stats:processed_count', 10);
    Redis::set('metrics:consumer:update_auction_stats:total_latency_ms', 500);

    $response = $this->getJson('/api/dashboard/technical')->assertOk();

    $response->assertJsonPath('data.websocket.connections', 7);

    $consumers = collect($response->json('data.consumers'));
    $stats = $consumers->firstWhere('name', 'update_auction_stats');

    expect($stats['queue'])->toBe('domain_events.update_auction_stats')
        ->and($stats['messages_ready'])->toBe(3)
        ->and($stats['messages_unacknowledged'])->toBe(1)
        ->and($stats['active_consumers'])->toBe(1)
        ->and($stats['processed_total'])->toBe(10)
        ->and($stats['avg_latency_ms'])->toBe(50);
});

test('a consumer with nothing processed yet reports zero average latency, not a division error', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    fakeConnectionsCounter(0);
    Http::fake(['*' => Http::response(['messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 0])]);

    $response = $this->getJson('/api/dashboard/technical')->assertOk();

    $stats = collect($response->json('data.consumers'))->firstWhere('name', 'update_auction_stats');
    expect($stats['processed_total'])->toBe(0)
        ->and($stats['avg_latency_ms'])->toBe(0);
});

test('a queue that has never been declared reports zero instead of an error', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    fakeConnectionsCounter(0);
    Http::fake(['*' => Http::response(['error' => 'Object Not Found'], 404)]);

    $this->getJson('/api/dashboard/technical')
        ->assertOk()
        ->assertJsonPath('data.consumers.0.messages_ready', 0);
});

test('the technical dashboard requires the dashboard:read ability', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['profile:read']);

    $this->getJson('/api/dashboard/technical')->assertForbidden();
});

test('the technical dashboard requires authentication', function () {
    $this->getJson('/api/dashboard/technical')->assertUnauthorized();
});
