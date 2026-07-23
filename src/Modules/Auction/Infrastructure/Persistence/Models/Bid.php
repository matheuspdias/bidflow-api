<?php

namespace App\Modules\Auction\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No belongsTo() to Modules\User's model — bidder_id is a plain foreign key
 * column, same reasoning as Auction::seller_id.
 *
 * @property \Illuminate\Support\Carbon $created_at
 */
#[Fillable(['auction_id', 'bidder_id', 'amount', 'status'])]
class Bid extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Auction, $this>
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }
}
