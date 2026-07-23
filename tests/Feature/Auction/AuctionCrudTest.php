<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Category;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Laravel\Sanctum\Sanctum;

test('anyone can list auctions', function () {
    Auction::factory()->count(3)->create();

    $this->getJson('/api/auctions')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('anyone can view a single auction', function () {
    $auction = Auction::factory()->create(['name' => 'Vintage Watch']);

    $this->getJson("/api/auctions/{$auction->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Vintage Watch')
        ->assertJsonPath('data.status', 'scheduled');
});

test('viewing a non-existent auction returns 404', function () {
    $this->getJson('/api/auctions/999999')->assertNotFound();
});

test('an authenticated seller can create an auction', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create();
    Sanctum::actingAs($seller, ['*']);

    $response = $this->postJson('/api/auctions', [
        'category_id' => $category->id,
        'name' => 'Vintage Watch',
        'description' => 'A fine vintage watch.',
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'buy_now_price' => 500,
        'reserve_price' => 150,
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Vintage Watch')
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.seller_id', $seller->id);

    $this->assertDatabaseHas('auctions', ['name' => 'Vintage Watch', 'seller_id' => $seller->id]);
});

test('creating an auction requires authentication', function () {
    $category = Category::factory()->create();

    $this->postJson('/api/auctions', [
        'category_id' => $category->id,
        'name' => 'Vintage Watch',
        'description' => 'A fine vintage watch.',
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ])->assertUnauthorized();
});

test('creating an auction validates the payload', function () {
    $seller = User::factory()->create();
    Sanctum::actingAs($seller, ['*']);

    $this->postJson('/api/auctions', [
        'category_id' => 999999,
        'name' => '',
        'description' => '',
        'starting_bid' => -10,
        'minimum_increment' => 0,
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->subDay()->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'name', 'description', 'starting_bid', 'minimum_increment', 'ends_at']);
});

test('the owner can update a scheduled auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    $category = Category::factory()->create();
    Sanctum::actingAs($seller, ['*']);

    $this->patchJson("/api/auctions/{$auction->id}", [
        'category_id' => $category->id,
        'name' => 'Updated name',
        'description' => 'Updated description',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(3)->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.name', 'Updated name');
});

test('a non-owner cannot update an auction', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    $category = Category::factory()->create();
    Sanctum::actingAs($stranger, ['*']);

    $this->patchJson("/api/auctions/{$auction->id}", [
        'category_id' => $category->id,
        'name' => 'Hijacked name',
        'description' => 'Updated description',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(3)->toIso8601String(),
    ])->assertForbidden();
});

test('an auction cannot be updated once it is active', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->active()->create(['seller_id' => $seller->id]);
    $category = Category::factory()->create();
    Sanctum::actingAs($seller, ['*']);

    $this->patchJson("/api/auctions/{$auction->id}", [
        'category_id' => $category->id,
        'name' => 'Updated name',
        'description' => 'Updated description',
        'starts_at' => now()->addDay()->toIso8601String(),
        'ends_at' => now()->addDays(3)->toIso8601String(),
    ])->assertUnprocessable();
});

test('the owner can activate a scheduled auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('auctions', ['id' => $auction->id, 'status' => 'active']);
});

test('an auction cannot be activated twice', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->active()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/activate")->assertUnprocessable();
});

test('a non-owner cannot activate an auction', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($stranger, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/activate")->assertForbidden();
});

test('the owner can cancel a scheduled or active auction', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('a cancelled auction cannot be cancelled again', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->cancelled()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['*']);

    $this->postJson("/api/auctions/{$auction->id}/cancel")->assertUnprocessable();
});

test('mutating endpoints require the auction:manage ability', function () {
    $seller = User::factory()->create();
    $auction = Auction::factory()->create(['seller_id' => $seller->id]);
    Sanctum::actingAs($seller, ['profile:read']);

    $this->postJson("/api/auctions/{$auction->id}/activate")->assertForbidden();
});
