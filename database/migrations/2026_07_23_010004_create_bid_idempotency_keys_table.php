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
        Schema::create('bid_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidder_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key');
            $table->unsignedSmallInteger('response_status');
            $table->text('response_body');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['bidder_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_idempotency_keys');
    }
};
