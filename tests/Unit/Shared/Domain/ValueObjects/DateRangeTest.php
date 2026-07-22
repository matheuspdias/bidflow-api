<?php

use App\Shared\Domain\ValueObjects\DateRange;

test('of throws when end is not after start', function () {
    $start = new DateTimeImmutable('2026-01-01 12:00:00');

    DateRange::of($start, $start);
})->throws(InvalidArgumentException::class);

test('of throws when end is before start', function () {
    $start = new DateTimeImmutable('2026-01-01 12:00:00');
    $end = new DateTimeImmutable('2026-01-01 11:00:00');

    DateRange::of($start, $end);
})->throws(InvalidArgumentException::class);

test('contains returns true for a moment inside the range, inclusive of edges', function () {
    $range = DateRange::of(
        new DateTimeImmutable('2026-01-01 10:00:00'),
        new DateTimeImmutable('2026-01-01 12:00:00'),
    );

    expect($range->contains(new DateTimeImmutable('2026-01-01 11:00:00')))->toBeTrue()
        ->and($range->contains(new DateTimeImmutable('2026-01-01 10:00:00')))->toBeTrue()
        ->and($range->contains(new DateTimeImmutable('2026-01-01 12:00:00')))->toBeTrue()
        ->and($range->contains(new DateTimeImmutable('2026-01-01 09:59:59')))->toBeFalse()
        ->and($range->contains(new DateTimeImmutable('2026-01-01 12:00:01')))->toBeFalse();
});

test('overlaps detects intersecting ranges', function () {
    $range = DateRange::of(
        new DateTimeImmutable('2026-01-01 10:00:00'),
        new DateTimeImmutable('2026-01-01 12:00:00'),
    );

    $overlapping = DateRange::of(
        new DateTimeImmutable('2026-01-01 11:00:00'),
        new DateTimeImmutable('2026-01-01 13:00:00'),
    );

    expect($range->overlaps($overlapping))->toBeTrue()
        ->and($overlapping->overlaps($range))->toBeTrue();
});

test('overlaps returns false for disjoint ranges', function () {
    $range = DateRange::of(
        new DateTimeImmutable('2026-01-01 10:00:00'),
        new DateTimeImmutable('2026-01-01 12:00:00'),
    );

    $disjoint = DateRange::of(
        new DateTimeImmutable('2026-01-01 13:00:00'),
        new DateTimeImmutable('2026-01-01 14:00:00'),
    );

    expect($range->overlaps($disjoint))->toBeFalse();
});

test('durationInSeconds returns the span between start and end', function () {
    $range = DateRange::of(
        new DateTimeImmutable('2026-01-01 10:00:00'),
        new DateTimeImmutable('2026-01-01 12:00:00'),
    );

    expect($range->durationInSeconds())->toBe(7200);
});
