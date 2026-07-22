<?php

declare(strict_types=1);

namespace App\Shared\Domain\Events;

use DateTimeImmutable;

/**
 * Marker for events raised by an aggregate during a single in-process
 * operation (e.g. BidPlaced). Collected via the aggregate's pullDomainEvents()
 * and only translated to an IntegrationEvent after the transaction commits.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
