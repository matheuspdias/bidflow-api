<?php

declare(strict_types=1);

namespace App\Modules\Auction\Presentation\Resources;

use App\Modules\Auction\Application\DTOs\BidPlacementResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BidResource extends JsonResource
{
    public function __construct(private readonly BidPlacementResult $result)
    {
        parent::__construct($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bid = $this->result->bid;
        $auction = $this->result->auction;

        return [
            'id' => $bid->id(),
            'auction_id' => $bid->auctionId(),
            'bidder_id' => $bid->bidderId(),
            'amount' => $bid->amount()->amount(),
            'placed_at' => $bid->placedAt()->format(DATE_ATOM),
            'auction' => [
                'status' => $auction->status()->value,
                'current_value' => $auction->currentValue()->amount(),
                'participant_count' => $auction->participantCount(),
            ],
        ];
    }
}
