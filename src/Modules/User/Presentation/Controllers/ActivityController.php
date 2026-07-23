<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Controllers;

use App\Modules\User\Domain\Repositories\UserRepository;
use App\Shared\Domain\Contracts\BidHistoryLookup;
use App\Shared\Domain\Contracts\BuyerRankingLookup;
use App\Shared\Domain\Contracts\WonLostAuctionsLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The authenticated user's activity — bid history, won/lost auctions,
 * buyer rankings (Fase 12). All four fetch their actual data from
 * Modules\Auction through Shared\Domain\Contracts (BidHistoryLookup,
 * WonLostAuctionsLookup, BuyerRankingLookup) — this module never touches
 * Auction's Eloquent models directly.
 */
final class ActivityController
{
    public function __construct(
        private readonly BidHistoryLookup $bidHistory,
        private readonly WonLostAuctionsLookup $wonLostAuctions,
        private readonly BuyerRankingLookup $buyerRankings,
        private readonly UserRepository $users,
    ) {
    }

    public function bidHistory(Request $request): JsonResponse
    {
        return response()->json($this->bidHistory->paginateForBidder(
            $request->user()->id,
            $this->page($request),
            $this->perPage($request),
        ));
    }

    public function auctionsWon(Request $request): JsonResponse
    {
        return response()->json($this->wonLostAuctions->paginateWon(
            $request->user()->id,
            $this->page($request),
            $this->perPage($request),
        ));
    }

    public function auctionsLost(Request $request): JsonResponse
    {
        return response()->json($this->wonLostAuctions->paginateLost(
            $request->user()->id,
            $this->page($request),
            $this->perPage($request),
        ));
    }

    /**
     * Enriches each ranking row (user_id, wins) with the buyer's name —
     * BuyerRankingLookup stays free of Modules\User's own concerns, so the
     * lookup happens here, in the module that already owns UserRepository.
     */
    public function rankings(Request $request): JsonResponse
    {
        $limit = min(50, max(1, $request->integer('limit', 10)));

        $rankings = array_map(function (array $row): array {
            $profile = $this->users->findById($row['user_id']);

            return [
                'user_id' => $row['user_id'],
                'name' => $profile?->name() ?? "User #{$row['user_id']}",
                'wins' => $row['wins'],
            ];
        }, $this->buyerRankings->topWinners($limit));

        return response()->json(['data' => $rankings]);
    }

    private function page(Request $request): int
    {
        return max(1, $request->integer('page', 1));
    }

    private function perPage(Request $request): int
    {
        return min(50, max(1, $request->integer('per_page', 15)));
    }
}
