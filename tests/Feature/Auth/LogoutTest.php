<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $newToken = $user->createToken('api');

    $this->withHeader('Authorization', "Bearer {$newToken->plainTextToken}")
        ->postJson('/api/logout')
        ->assertNoContent();

    // A follow-up authenticated request within the same test would still pass
    // here, since Laravel's RequestGuard caches the resolved user for the
    // lifetime of the test's $app instance — not a bug in the endpoint, a
    // limitation of simulating two requests in one test process. Assert the
    // token row is actually gone instead.
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $newToken->accessToken->id]);
});

test('logout requires authentication', function () {
    $this->postJson('/api/logout')->assertUnauthorized();
});
