<?php

declare(strict_types=1);

namespace App\Modules\Auction\Presentation\Resources;

use App\Modules\Auction\Domain\Aggregates\Auction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuctionResource extends JsonResource
{
    public function __construct(private readonly Auction $auction)
    {
        parent::__construct($auction);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->auction->id(),
            'seller_id' => $this->auction->sellerId(),
            'category_id' => $this->auction->categoryId(),
            'name' => $this->auction->name(),
            'description' => $this->auction->description(),
            'currency' => $this->auction->currentValue()->currency(),
            'starting_bid' => $this->auction->startingBid()->amount(),
            'minimum_increment' => $this->auction->minimumIncrement()->amount(),
            'buy_now_price' => $this->auction->buyNowPrice()?->amount(),
            'reserve_price' => $this->auction->reservePrice()?->amount(),
            'status' => $this->auction->status()->value,
            'starts_at' => $this->auction->dateRange()->start->format(DATE_ATOM),
            'ends_at' => $this->auction->dateRange()->end->format(DATE_ATOM),
            'current_value' => $this->auction->currentValue()->amount(),
            'participant_count' => $this->auction->participantCount(),
            'view_count' => $this->auction->viewCount(),
            'extensions_count' => $this->auction->extensionsCount(),
        ];
    }
}
