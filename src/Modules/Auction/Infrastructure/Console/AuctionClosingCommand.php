<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Console;

use App\Modules\Auction\Application\UseCases\CloseAuctionUseCase;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * A dedicated long-running process, like AuctionTimerBroadcastCommand — not
 * a RabbitMqConsumerCommand, nothing publishes an event to react to here.
 * Unlike the timer, closing genuinely changes state (CLOSED, a winner), so
 * each candidate id is re-fetched and locked individually via
 * CloseAuctionUseCase rather than acted on on the strength of this
 * unlocked polling query alone.
 */
final class AuctionClosingCommand extends Command
{
    protected $signature = 'auctions:close-ended
        {--interval=5 : seconds between ticks}
        {--iterations=0 : stop after this many ticks (0 = run forever)}';

    protected $description = 'Close ACTIVE auctions whose ends_at has passed, determining the winner';

    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly CloseAuctionUseCase $closeAuctionUseCase,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $iterations = (int) $this->option('iterations');

        $tick = 0;

        while (true) {
            foreach ($this->auctions->activeIdsEndingBefore(Carbon::now()->toDateTimeImmutable()) as $auctionId) {
                $this->closeAuctionUseCase->execute($auctionId);
            }

            $tick++;

            if ($iterations > 0 && $tick >= $iterations) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
