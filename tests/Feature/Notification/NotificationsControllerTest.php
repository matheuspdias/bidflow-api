<?php

use App\Modules\Notification\Infrastructure\Persistence\Models\Notification;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('an authenticated user can list their own notifications', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    Notification::create(['user_id' => $user->id, 'type' => 'outbid', 'data' => ['auction_id' => 1], 'created_at' => now()]);
    Notification::create(['user_id' => $stranger->id, 'type' => 'outbid', 'data' => ['auction_id' => 2], 'created_at' => now()]);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'outbid');
});

test('a user can mark their own notification as read', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $notification = Notification::create(['user_id' => $user->id, 'type' => 'outbid', 'data' => [], 'created_at' => now()]);

    $this->postJson("/api/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id, 'read_at' => null]);
});

test('a user cannot mark someone else notification as read', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    $notification = Notification::create(['user_id' => $owner->id, 'type' => 'outbid', 'data' => [], 'created_at' => now()]);

    $this->postJson("/api/notifications/{$notification->id}/read")->assertNotFound();
});

test('notification endpoints require the notifications:read ability', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['profile:read']);

    $this->getJson('/api/notifications')->assertForbidden();
});

test('notification endpoints require authentication', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});
