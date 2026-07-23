<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Infrastructure\Console;

use App\Modules\Dashboard\Infrastructure\Broadcast\TechnicalMetricsUpdatedBroadcastEvent;
use App\Modules\Dashboard\Infrastructure\ReadModels\TechnicalMetrics;
use Illuminate\Console\Command;

/**
 * A dedicated long-running process, like BroadcastBusinessMetricsCommand —
 * not a RabbitMQ consumer. Ticks every 5 seconds, recomputing the whole
 * snapshot (WS connections, per-consumer queue depth/throughput/latency)
 * each time.
 */
final class BroadcastTechnicalMetricsCommand extends Command
{
    protected $signature = 'dashboard:broadcast-technical
        {--interval=5 : seconds between ticks}
        {--iterations=0 : stop after this many ticks (0 = run forever)}';

    protected $description = 'Broadcast a snapshot of technical metrics for the ops dashboard';

    public function __construct(private readonly TechnicalMetrics $metrics)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $iterations = (int) $this->option('iterations');

        $tick = 0;

        while (true) {
            broadcast(new TechnicalMetricsUpdatedBroadcastEvent($this->metrics->current()));

            $tick++;

            if ($iterations > 0 && $tick >= $iterations) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
