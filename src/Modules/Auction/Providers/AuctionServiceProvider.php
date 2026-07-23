<?php

declare(strict_types=1);

namespace App\Modules\Auction\Providers;

use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionClosed;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Domain\Events\UserJoinedAuction;
use App\Modules\Auction\Domain\Events\UserLeftAuction;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\Repositories\BidAuditLogRepository;
use App\Modules\Auction\Domain\Repositories\BidRepository;
use App\Modules\Auction\Infrastructure\Console\AuctionClosingCommand;
use App\Modules\Auction\Infrastructure\Console\AuctionTimerBroadcastCommand;
use App\Modules\Auction\Infrastructure\Console\Consumers\BroadcastAuctionEndedConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\BroadcastAuctionExtendedConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\BroadcastBidConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\BroadcastViewerCountConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\PersistBidHistoryConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\SendBidNotificationConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\SendWonNotificationConsumer;
use App\Modules\Auction\Infrastructure\Console\Consumers\UpdateAuctionStatsConsumer;
use App\Modules\Auction\Infrastructure\Listeners\PublishAuctionCancelledIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishAuctionClosedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishAuctionExtendedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishAuctionStartedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishBidPlacedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishUserJoinedAuctionIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\PublishUserLeftAuctionIntegrationEvent;
use App\Modules\Auction\Infrastructure\Listeners\RecordFirstPresenceMember;
use App\Modules\Auction\Infrastructure\Listeners\ReleasePresenceOnChannelEmpty;
use App\Modules\Auction\Infrastructure\Listeners\TrackPresenceChannelMembership;
use App\Modules\Auction\Infrastructure\Repositories\EloquentAuctionRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentBidAuditLogRepository;
use App\Modules\Auction\Infrastructure\Repositories\EloquentBidRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageSent;

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
        Event::listen(AuctionExtended::class, PublishAuctionExtendedIntegrationEvent::class);
        Event::listen(AuctionClosed::class, PublishAuctionClosedIntegrationEvent::class);
        Event::listen(UserJoinedAuction::class, PublishUserJoinedAuctionIntegrationEvent::class);
        Event::listen(UserLeftAuction::class, PublishUserLeftAuctionIntegrationEvent::class);

        // Reverb's own internal Laravel events (fired inside the reverb:start
        // process, not ours) — the presence-tracking substitute for the
        // webhooks Reverb doesn't have. See TrackPresenceChannelMembership
        // and ADR-0012.
        Event::listen(MessageSent::class, TrackPresenceChannelMembership::class);
        Event::listen(ChannelRemoved::class, ReleasePresenceOnChannelEmpty::class);
        Event::listen(ChannelCreated::class, RecordFirstPresenceMember::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateAuctionStatsConsumer::class,
                PersistBidHistoryConsumer::class,
                SendBidNotificationConsumer::class,
                BroadcastBidConsumer::class,
                BroadcastViewerCountConsumer::class,
                BroadcastAuctionExtendedConsumer::class,
                BroadcastAuctionEndedConsumer::class,
                SendWonNotificationConsumer::class,
                AuctionTimerBroadcastCommand::class,
                AuctionClosingCommand::class,
            ]);
        }
    }
}
