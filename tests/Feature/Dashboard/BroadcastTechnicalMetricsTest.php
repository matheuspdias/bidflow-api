<?php

use App\Modules\Dashboard\Infrastructure\ReadModels\WebSocketConnectionsCounter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('dashboard:broadcast-technical broadcasts a metrics snapshot on the dashboard-technical channel', function () {
    config(['broadcasting.default' => 'log']);
    Log::spy();

    $this->app->instance(WebSocketConnectionsCounter::class, new class extends WebSocketConnectionsCounter
    {
        public function count(): int
        {
            return 2;
        }
    });

    Http::fake(['*' => Http::response(['messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 1])]);

    $this->artisan('dashboard:broadcast-technical', ['--iterations' => 1])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) {
        return str_contains($message, 'Broadcasting [technical.updated]')
            && str_contains($message, 'private-dashboard-technical')
            && str_contains($message, '"connections": 2');
    })->once();
});
