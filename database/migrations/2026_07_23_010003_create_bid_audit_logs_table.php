<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bid_audit_logs', function (Blueprint $table) {
            $table->id();
            // No FK on auction_id: attempts against a bogus/non-existent
            // auction id must still be logged, not blocked by referential
            // integrity.
            $table->unsignedBigInteger('auction_id');
            $table->foreignId('bidder_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('attempted_amount', 12, 2);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('result');
            $table->string('reason')->nullable();
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
        Schema::dropIfExists('bid_audit_logs');
    }
};
