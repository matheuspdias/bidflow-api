<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * A denormalized read model, populated asynchronously by
     * PersistBidHistoryConsumer — deliberately separate from the
     * transactional `bids` table (Fase 4), which is the write-side source
     * of truth. No FKs on purpose: this table is a projection, not a
     * participant in referential integrity (see ADR-0010).
     */
    public function up(): void
    {
        Schema::create('bid_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->unsignedBigInteger('auction_id');
            $table->unsignedBigInteger('bidder_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('auction_id');
            $table->index('bidder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_history');
    }
};
