<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\MessageBroker;

use App\Shared\Domain\Events\IntegrationEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The publish path every Infrastructure\Listeners translation listener
 * should go through — never RabbitMqPublisher directly. A broker outage
 * must never surface as an exception to the request that already committed
 * the domain event; it's recorded for replay instead (ADR-0008).
 */
final class SafeIntegrationEventPublisher
{
    public function __construct(
        private readonly RabbitMqPublisher $publisher,
        private readonly FailedIntegrationEventRepository $failedEvents,
    ) {
    }

    public function publish(IntegrationEvent $event): void
    {
        try {
            $this->publisher->publish($event);
        } catch (Throwable $exception) {
            $this->failedEvents->record($event, $exception);

            Log::error('Failed to publish integration event.', [
                'event_id' => $event->eventId(),
                'routing_key' => $event->routingKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
