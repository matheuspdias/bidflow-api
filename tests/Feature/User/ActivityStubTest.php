<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('activity stub endpoints return empty collections until Fase 12', function (string $endpoint) {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson($endpoint)
        ->assertOk()
        ->assertJson(['data' => []]);
})->with([
    '/api/profile/bids',
    '/api/profile/auctions/won',
    '/api/profile/auctions/lost',
    '/api/rankings',
]);
