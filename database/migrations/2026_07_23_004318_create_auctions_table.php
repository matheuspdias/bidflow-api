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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->text('description');
            $table->decimal('starting_bid', 12, 2);
            $table->decimal('minimum_increment', 12, 2);
            $table->decimal('buy_now_price', 12, 2)->nullable();
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->decimal('current_value', 12, 2);
            $table->unsignedInteger('participant_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('seller_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
