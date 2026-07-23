<?php

namespace App\Modules\Notification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately has no belongsTo() to Modules\User's model — user_id is a
 * plain foreign key column, same reasoning as Auction::seller_id.
 *
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property array<string, mixed> $data
 */
#[Fillable(['user_id', 'type', 'data', 'read_at', 'created_at'])]
class Notification extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
