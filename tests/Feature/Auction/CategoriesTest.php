<?php

use App\Modules\Auction\Infrastructure\Persistence\Models\Category;

test('anyone can list categories', function () {
    Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    Category::factory()->create(['name' => 'Art', 'slug' => 'art']);

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => 'Electronics']);
});
