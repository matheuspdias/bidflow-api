<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auction::close() (Fase 11) computed a winner but never persisted it
 * anywhere queryable — only the transient AuctionClosed event payload knew,
 * consumed once by the broadcast/notification consumers and then gone.
 * Fase 12's "auctions I've won/lost" and buyer rankings need this to be a
 * real column, not something re-derived at query time from bids +
 * reserve_price (that logic already lives in the aggregate; duplicating it
 * in a query would be the same rule in two places).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->foreignId('winner_id')->nullable()->after('highest_bid_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_id');
        });
    }
};
