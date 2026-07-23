<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console;

use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use App\Modules\Auction\Infrastructure\Broadcast\TimerUpdatedBroadcastEvent;
use App\Modules\Auction\Infrastructure\Persistence\Models\Auction as AuctionModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * A dedicated long-running process (its own docker-compose service, like
 * Reverb/Horizon), not a RabbitMqConsumerCommand — nothing publishes an
 * event to react to here, this is a clock, not a message handler.
 *
 * Queries the Eloquent model directly rather than hydrating full Auction
 * aggregates through AuctionRepository: this runs every tick for every
 * auction in its final stretch, and none of the aggregate's business rules
 * (Money, DateRange, bid validation) are needed just to read an id and an
 * ends_at column for a broadcast.
 */
final class AuctionTimerBroadcastCommand extends Command
{
    protected $signature = 'auctions:broadcast-timer
        {--interval=1 : seconds between ticks}
        {--iterations=0 : stop after this many ticks (0 = run forever)}';

    protected $description = 'Broadcast a synchronized timer.updated tick for active auctions nearing their end';

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $iterations = (int) $this->option('iterations');
        $windowSeconds = (int) config('auctions.timer.broadcast_window_seconds');

        $tick = 0;

        while (true) {
            $now = Carbon::now();

            AuctionModel::query()
                ->where('status', AuctionStatus::ACTIVE->value)
                ->where('ends_at', '<=', $now->copy()->addSeconds($windowSeconds))
                ->get(['id', 'ends_at'])
                ->each(function (AuctionModel $auction) use ($now): void {
                    broadcast(new TimerUpdatedBroadcastEvent(
                        auctionId: $auction->id,
                        secondsRemaining: max(0, $auction->ends_at->getTimestamp() - $now->getTimestamp()),
                        endsAt: $auction->ends_at->format(DATE_ATOM),
                    ));
                });

            $tick++;

            if ($iterations > 0 && $tick >= $iterations) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
