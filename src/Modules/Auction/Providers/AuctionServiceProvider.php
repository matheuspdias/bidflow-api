<?php

declare(strict_types=1);

namespace App\Modules\Auction\Providers;

use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidAuditLogRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentAuctionRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentBidAuditLogRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentBidRepository;
use Illuminate\Support\ServiceProvider;

class AuctionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuctionRepository::class, EloquentAuctionRepository::class);
        $this->app->bind(BidRepository::class, EloquentBidRepository::class);
        $this->app->bind(BidAuditLogRepository::class, EloquentBidAuditLogRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
