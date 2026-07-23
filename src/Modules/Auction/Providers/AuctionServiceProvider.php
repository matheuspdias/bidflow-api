<?php

declare(strict_types=1);

namespace App\Modules\Auction\Providers;

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentAuctionRepository;
use Illuminate\Support\ServiceProvider;

class AuctionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuctionRepository::class, EloquentAuctionRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
