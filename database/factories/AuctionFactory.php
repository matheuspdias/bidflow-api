<?php

namespace Database\Factories;

use App\Modules\Auction\Infrastructure\Persistence\Models\Auction;
use App\Modules\Auction\Infrastructure\Persistence\Models\Category;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starting_bid' => 100,
            'minimum_increment' => 10,
            'buy_now_price' => null,
            'reserve_price' => null,
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'current_value' => 100,
            'participant_count' => 0,
            'view_count' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
