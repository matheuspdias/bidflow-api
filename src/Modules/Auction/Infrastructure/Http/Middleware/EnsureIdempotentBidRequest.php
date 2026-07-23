<?php

declare(strict_types=1);

namespace App\Modules\Auction\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an Idempotency-Key header on bid placement. A replay of a key
 * already seen for this bidder returns the cached response instead of
 * re-running the use case — the client-visible half of the idempotency
 * strategy described in ADR-0007.
 *
 * This is a check-then-act sequence, not race-proof against two concurrent
 * requests carrying the *same* key landing at the same instant (a narrower
 * scenario than the bid-placement concurrency guaranteed by the pessimistic
 * lock in PlaceBidUseCase). Acceptable here: retries of the same request are
 * expected to be seconds apart (network timeout, client retry), not
 * simultaneous.
 */
final class EnsureIdempotentBidRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return response()->json(['message' => 'The Idempotency-Key header is required.'], 400);
        }

        $bidderId = $request->user()->id;

        $cached = DB::table('bid_idempotency_keys')
            ->where('bidder_id', $bidderId)
            ->where('idempotency_key', $key)
            ->first();

        if ($cached !== null) {
            return response()->json(json_decode($cached->response_body, true), $cached->response_status);
        }

        $response = $next($request);

        DB::table('bid_idempotency_keys')->insert([
            'bidder_id' => $bidderId,
            'idempotency_key' => $key,
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'created_at' => now(),
        ]);

        return $response;
    }
}
