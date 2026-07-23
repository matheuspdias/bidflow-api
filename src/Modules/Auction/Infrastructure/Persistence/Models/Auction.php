<?php

namespace App\Modules\Auction\Infrastructure\Persistence\Models;

use Database\Factories\AuctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deliberately has no belongsTo() relationship to Modules\User's Eloquent
 * model — seller_id is a plain foreign key column, resolved through
 * Shared\Domain\Contracts\SellerLookup when seller data is actually needed,
 * to keep this module from depending on another module's internals.
 *
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 */
#[Fillable([
    'seller_id',
    'category_id',
    'name',
    'description',
    'starting_bid',
    'minimum_increment',
    'buy_now_price',
    'reserve_price',
    'status',
    'starts_at',
    'ends_at',
    'current_value',
    'participant_count',
    'view_count',
    'highest_bid_id',
    'extensions_count',
])]
class Auction extends Model
{
    /** @use HasFactory<AuctionFactory> */
    use HasFactory;

    protected static function newFactory(): AuctionFactory
    {
        return AuctionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'starting_bid' => 'decimal:2',
            'minimum_increment' => 'decimal:2',
            'buy_now_price' => 'decimal:2',
            'reserve_price' => 'decimal:2',
            'current_value' => 'decimal:2',
            'participant_count' => 'integer',
            'view_count' => 'integer',
            'extensions_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<AuctionImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(AuctionImage::class)->orderBy('position');
    }
}
