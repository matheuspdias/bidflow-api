<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Console;

use App\Modules\Dashboard\Infrastructure\Broadcast\BusinessMetricsUpdatedBroadcastEvent;
use App\Shared\Domain\Contracts\BusinessMetricsLookup;
use Illuminate\Console\Command;

/**
 * A dedicated long-running process, like AuctionTimerBroadcastCommand/
 * AuctionClosingCommand — not a RabbitMQ consumer, nothing publishes an
 * event to react to here. Ticks every 5 seconds (configurable),
 * recomputing and broadcasting the whole metrics snapshot each time —
 * simpler than tracking which individual counters changed since the last
 * tick, and cheap enough at this scale (a handful of aggregate queries).
 */
final class BroadcastBusinessMetricsCommand extends Command
{
    protected $signature = 'dashboard:broadcast-business
        {--interval=5 : seconds between ticks}
        {--iterations=0 : stop after this many ticks (0 = run forever)}';

    protected $description = 'Broadcast a snapshot of business metrics for the admin dashboard';

    public function __construct(private readonly BusinessMetricsLookup $metrics)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $iterations = (int) $this->option('iterations');

        $tick = 0;

        while (true) {
            broadcast(new BusinessMetricsUpdatedBroadcastEvent($this->metrics->current()));

            $tick++;

            if ($iterations > 0 && $tick >= $iterations) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
