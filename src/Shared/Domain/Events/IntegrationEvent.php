<?php

declare(strict_types=1);

namespace App\Shared\Domain\Events;

use DateTimeImmutable;

/**
 * Marker for events published across module/process boundaries via the
 * message broker (see Shared\Infrastructure\MessageBroker). Translated from
 * a DomainEvent by an Infrastructure listener, never raised directly by the
 * domain layer.
 */
interface IntegrationEvent
{
    public function eventId(): string;

    public function occurredAt(): DateTimeImmutable;

    public function routingKey(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
