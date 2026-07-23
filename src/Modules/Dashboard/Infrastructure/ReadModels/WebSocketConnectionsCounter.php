<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\ReadModels;

use Illuminate\Support\Facades\Broadcast;

/**
 * Its own class, not inlined into TechnicalMetrics, purely so tests can
 * swap it out: the underlying call goes through pusher-php-server's own
 * Guzzle client (Broadcast::connection('reverb')->getPusher()->get(...)),
 * which Laravel's Http::fake() has no visibility into — it only intercepts
 * Illuminate\Http\Client's own handler. Binding a fake implementation of
 * this one-method class in tests is simpler than making Reverb's real HTTP
 * API reachable (or convincingly faked) from the test suite.
 */
class WebSocketConnectionsCounter
{
    public function count(): int
    {
        $response = Broadcast::connection('reverb')->getPusher()->get('/connections');

        return (int) ($response->connections ?? 0);
    }
}
