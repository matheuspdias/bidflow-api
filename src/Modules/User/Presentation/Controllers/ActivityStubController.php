<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Stub endpoints for the authenticated user's activity. Real
 * implementations land in Fase 12 (GetBidHistoryQuery, GetAuctionsWonQuery,
 * GetAuctionsLostQuery, ranking queries) once auctions actually resolve.
 * Kept here now so the API surface is stable before the frontend
 * integrates against it.
 */
final class ActivityStubController
{
    /**
     * List the authenticated user's bid history.
     *
     * @todo Fase 12: replace with GetBidHistoryQuery.
     */
    public function bidHistory(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /**
     * List auctions the authenticated user has won.
     *
     * @todo Fase 12: replace with GetAuctionsWonQuery.
     */
    public function auctionsWon(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /**
     * List auctions the authenticated user has lost.
     *
     * @todo Fase 12: replace with GetAuctionsLostQuery.
     */
    public function auctionsLost(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    /**
     * Buyer rankings (methodology documented in the README once implemented).
     *
     * @todo Fase 12: replace with ranking queries.
     */
    public function rankings(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
