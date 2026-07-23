<?php

declare(strict_types=1);

namespace App\Modules\Auction\Providers;

use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidAuditLogRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Modules\Auction\Infrastructure\Console\Consumers\BroadcastBidConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\PersistBidHistoryConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\SendBidNotificationConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\UpdateAuctionStatsConsumer;
use App\Modules\Auction\Infrastructure\Listeners\PublishAuctionCancelledIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishAuctionStartedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishBidPlacedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Repositories\EloquentAuctionRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentBidAuditLogRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentBidRepository;
use Illuminate\Support\Facades\Event;
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
        Event::listen(BidPlaced::class, PublishBidPlacedIntegrationEvent::class);
        Event::listen(AuctionStarted::class, PublishAuctionStartedIntegrationEvent::class);
        Event::listen(AuctionCancelled::class, PublishAuctionCancelledIntegrationEvent::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateAuctionStatsConsumer::class,
                PersistBidHistoryConsumer::class,
                SendBidNotificationConsumer::class,
                BroadcastBidConsumer::class,
            ]);
        }
    }
}
