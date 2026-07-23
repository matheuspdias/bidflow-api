<?php

declare(strict_types=1);

namespace App\Modules\Auction\Presentation\Controllers;

use App\Modules\Auction\Application\UseCases\ActivateAuctionUseCase;
use App\Modules\Auction\Application\UseCases\CancelAuctionUseCase;
use App\Modules\Auction\Application\UseCases\CreateAuctionUseCase;
use App\Modules\Auction\Application\UseCases\UpdateAuctionUseCase;
use App\Modules\Auction\Domain\Exceptions\AuctionNotFoundException;
use App\Modules\Auction\Domain\Exceptions\InvalidAuctionStatusTransitionException;
use App\Modules\Auction\Domain\Exceptions\NotAuctionOwnerException;
use App\Modules\Auction\Domain\Repositories\AuctionRepository;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use App\Modules\Auction\Infrastructure\ReadModels\RecentBidsFeed;
use App\Modules\Auction\Presentation\Requests\CreateAuctionRequest;
use App\Modules\Auction\Presentation\Requests\UpdateAuctionRequest;
use App\Modules\Auction\Presentation\Resources\AuctionResource;
use App\Shared\Domain\Contracts\UserIdentity;
use App\Shared\Domain\ValueObjects\DateRange;
use App\Shared\Domain\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

final class AuctionsController
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly CreateAuctionUseCase $createAuctionUseCase,
        private readonly UpdateAuctionUseCase $updateAuctionUseCase,
        private readonly ActivateAuctionUseCase $activateAuctionUseCase,
        private readonly CancelAuctionUseCase $cancelAuctionUseCase,
        private readonly RecentBidsFeed $recentBidsFeed,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->filled('status') ? AuctionStatus::from((string) $request->string('status')) : null;
        $categoryId = $request->integer('category_id') ?: null;
        $page = max(1, $request->integer('page', 1));
        $perPage = min(50, max(1, $request->integer('per_page', 15)));

        $result = $this->auctions->paginate($page, $perPage, $status, $categoryId);

        return response()->json([
            'data' => AuctionResource::collection($result->items),
            'meta' => [
                'total' => $result->total,
                'per_page' => $result->perPage,
                'current_page' => $result->currentPage,
            ],
        ]);
    }

    public function show(int $id): AuctionResource|JsonResponse
    {
        $auction = $this->auctions->findById($id);

        if ($auction === null) {
            return response()->json(['message' => 'Auction not found.'], 404);
        }

        return new AuctionResource($auction);
    }

    /**
     * The one-shot "give me everything to render the live page" call —
     * complements the WS event stream (which only pushes deltas from here
     * on), it doesn't replace it. viewer_count is live/ephemeral (Fase 8's
     * Redis presence set); the resource's own participant_count/view_count
     * are unrelated, persisted counters — see RecentBidsFeed and
     * docs/websocket-events.md for how each stays in sync afterward.
     */
    public function live(int $id): JsonResponse
    {
        $auction = $this->auctions->findById($id);

        if ($auction === null) {
            return response()->json(['message' => 'Auction not found.'], 404);
        }

        return response()->json([
            'auction' => (new AuctionResource($auction))->resolve(),
            'viewer_count' => (int) Redis::scard("auction:{$id}:viewers"),
            'recent_bids' => $this->recentBidsFeed->forAuction($id),
        ]);
    }

    public function store(CreateAuctionRequest $request): JsonResponse
    {
        $currency = config('money.default_currency');

        $auction = $this->createAuctionUseCase->execute(
            sellerId: $request->user()->id,
            categoryId: (int) $request->validated('category_id'),
            name: (string) $request->validated('name'),
            description: (string) $request->validated('description'),
            startingBid: Money::of($request->validated('starting_bid'), $currency),
            minimumIncrement: Money::of($request->validated('minimum_increment'), $currency),
            buyNowPrice: $request->validated('buy_now_price') !== null ? Money::of($request->validated('buy_now_price'), $currency) : null,
            reservePrice: $request->validated('reserve_price') !== null ? Money::of($request->validated('reserve_price'), $currency) : null,
            schedule: DateRange::of(
                new DateTimeImmutable((string) $request->validated('starts_at')),
                new DateTimeImmutable((string) $request->validated('ends_at')),
            ),
        );

        return (new AuctionResource($auction))->response()->setStatusCode(201);
    }

    public function update(UpdateAuctionRequest $request, int $id, UserIdentity $identity): AuctionResource|JsonResponse
    {
        try {
            $auction = $this->updateAuctionUseCase->execute(
                auctionId: $id,
                requester: $identity,
                name: (string) $request->validated('name'),
                description: (string) $request->validated('description'),
                categoryId: (int) $request->validated('category_id'),
                schedule: DateRange::of(
                    new DateTimeImmutable((string) $request->validated('starts_at')),
                    new DateTimeImmutable((string) $request->validated('ends_at')),
                ),
            );
        } catch (AuctionNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (NotAuctionOwnerException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (InvalidAuctionStatusTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new AuctionResource($auction);
    }

    public function activate(int $id, UserIdentity $identity): AuctionResource|JsonResponse
    {
        try {
            $auction = $this->activateAuctionUseCase->execute($id, $identity);
        } catch (AuctionNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (NotAuctionOwnerException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (InvalidAuctionStatusTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new AuctionResource($auction);
    }

    public function cancel(int $id, UserIdentity $identity): AuctionResource|JsonResponse
    {
        try {
            $auction = $this->cancelAuctionUseCase->execute($id, $identity);
        } catch (AuctionNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (NotAuctionOwnerException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (InvalidAuctionStatusTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new AuctionResource($auction);
    }
}
