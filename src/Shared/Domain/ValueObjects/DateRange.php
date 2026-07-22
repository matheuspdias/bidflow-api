<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final class DateRange
{
    private function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
    ) {
    }

    public static function of(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('DateRange end must be after start.');
        }

        return new self($start, $end);
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->start && $moment <= $this->end;
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function durationInSeconds(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }
}
