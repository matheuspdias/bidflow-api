<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use Illuminate\Support\Facades\Log;

/**
 * Same Log-spy strategy as the other broadcast tests — no Broadcast::fake()
 * in this Laravel version (see BroadcastBidConsumerTest's docblock).
 */
test('dashboard:broadcast-business broadcasts a metrics snapshot on the dashboard channel', function () {
    config(['broadcasting.default' => 'log']);
    Log::spy();

    Auction::factory()->active()->create();

    $this->artisan('dashboard:broadcast-business', ['--iterations' => 1])->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(function (string $message) {
        return str_contains($message, 'Broadcasting [dashboard.updated]')
            && str_contains($message, 'private-dashboard')
            && str_contains($message, '"active": 1');
    })->once();
});
