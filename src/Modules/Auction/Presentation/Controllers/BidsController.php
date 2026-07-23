<?php

declare(strict_types=1);

namespace App\Modules\Auction\Presentation\Controllers;

use App\Modules\Auction\Application\UseCases\PlaceBidUseCase;
use App\Modules\Auction\Domain\Exceptions\AuctionClosedException;
use App\Modules\Auction\Domain\Exceptions\AuctionNotFoundException;
use App\Modules\Auction\Domain\Exceptions\BidderBlockedException;
use App\Modules\Auction\Domain\Exceptions\BidTooLowException;
use App\Modules\Auction\Domain\Exceptions\SellerCannotBidException;
use App\Modules\Auction\Presentation\Requests\PlaceBidRequest;
use App\Modules\Auction\Presentation\Resources\BidResource;
use App\Shared\Domain\ValueObjects\Money;
use Illuminate\Http\JsonResponse;

final class BidsController
{
    public function __construct(private readonly PlaceBidUseCase $placeBidUseCase)
    {
    }

    public function store(PlaceBidRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->placeBidUseCase->execute(
                auctionId: $id,
                bidderId: $request->user()->id,
                amount: Money::of((string) $request->validated('amount'), config('money.default_currency')),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (AuctionNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (BidderBlockedException|SellerCannotBidException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (AuctionClosedException|BidTooLowException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new BidResource($result))->response()->setStatusCode(201);
    }
}
