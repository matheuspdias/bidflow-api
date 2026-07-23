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
        Schema::create('failed_integration_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('routing_key');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('event_id');
            $table->index('routing_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_integration_events');
    }
};
