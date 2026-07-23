<?php

use App\Modules\Auction\Application\UseCases\PlaceBidUseCase;
use App\Modules\Auction\Infrastructure\Persistence\Models\Auction as AuctionModel;
use App\Modules\Auction\Infrastructure\Persistence\Models\Bid as BidModel;
use App\Modules\User\Infrastructure\Persistence\Models\User;
use App\Shared\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * The most important test in the whole codebase (see ADR-0006): proves the
 * pessimistic lock in PlaceBidUseCase actually serializes concurrent bids
 * instead of allowing a lost update. Bypasses HTTP entirely and forks real
 * OS processes — a single PHP test process can't produce genuine
 * concurrency, and mocking the lock away would prove nothing.
 */
test('exactly one bid is accepted when many bidders attempt the same amount concurrently', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl extension is required for this test.');
    }

    $seller = User::factory()->create();
    $auction = AuctionModel::factory()->active()->create([
        'seller_id' => $seller->id,
        'starting_bid' => 100,
        'minimum_increment' => 10,
        'current_value' => 100,
    ]);

    $bidderCount = 10;
    $bidderIds = User::factory()->count($bidderCount)->create()->pluck('id')->all();
    $contestedAmount = '150.00';

    $resultsDir = sys_get_temp_dir().'/bidflow-concurrency-'.uniqid();
    mkdir($resultsDir);

    try {
        // Every child inherits this connection's socket at fork time; close
        // it here so each child is forced to open its own on first use.
        DB::disconnect();

        $pids = [];

        foreach ($bidderIds as $bidderId) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Could not fork a process for the concurrency test.');
            }

            if ($pid === 0) {
                // Child process: fresh connection, fresh container-resolved
                // use case instance, then report the outcome to a file
                // (simpler and more robust than pipes for this many children).
                DB::reconnect();

                $outcome = 'error';

                try {
                    app(PlaceBidUseCase::class)->execute(
                        auctionId: $auction->id,
                        bidderId: $bidderId,
                        amount: Money::of($contestedAmount, 'USD'),
                        ipAddress: '127.0.0.1',
                        userAgent: 'concurrency-test',
                    );
                    $outcome = 'accepted';
                } catch (\Throwable) {
                    $outcome = 'rejected';
                }

                file_put_contents("{$resultsDir}/{$bidderId}.result", $outcome);

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $outcomes = array_map(
            fn (int $bidderId) => trim((string) file_get_contents("{$resultsDir}/{$bidderId}.result")),
            $bidderIds,
        );

        $accepted = array_filter($outcomes, fn (string $outcome) => $outcome === 'accepted');
        $rejected = array_filter($outcomes, fn (string $outcome) => $outcome === 'rejected');

        expect($outcomes)->toHaveCount($bidderCount)
            ->and($accepted)->toHaveCount(1)
            ->and($rejected)->toHaveCount($bidderCount - 1);

        // No lost update: the auction's current_value reflects exactly the
        // one accepted bid, and exactly one bid row exists for it.
        $auction->refresh();
        expect((string) $auction->current_value)->toBe($contestedAmount);

        $bidsForAuction = BidModel::where('auction_id', $auction->id)->get();
        expect($bidsForAuction)->toHaveCount(1)
            ->and((string) $bidsForAuction->first()->amount)->toBe($contestedAmount);
    } finally {
        BidModel::where('auction_id', $auction->id)->delete();
        AuctionModel::whereKey($auction->id)->delete();
        User::whereIn('id', [...$bidderIds, $seller->id])->delete();

        array_map('unlink', glob("{$resultsDir}/*") ?: []);
        rmdir($resultsDir);
    }
});
