<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marker for a use-case input dispatched through the CommandBus. Deliberately
 * empty — a hand-rolled bus, not a package, since a monolith of this size
 * doesn't need more than "map a command to its handler".
 */
interface Command
{
}
