<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\MessageBroker;

use App\Shared\Domain\Events\IntegrationEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persists a publish failure for later replay, instead of letting it bubble
 * up and roll back an already-committed business transaction (see
 * ADR-0008/ADR-0006).
 */
final class FailedIntegrationEventRepository
{
    public function record(IntegrationEvent $event, Throwable $exception): void
    {
        DB::table('failed_integration_events')->insert([
            'event_id' => $event->eventId(),
            'routing_key' => $event->routingKey(),
            'payload' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'exception' => $exception->getMessage(),
            'created_at' => now(),
        ]);
    }
}
