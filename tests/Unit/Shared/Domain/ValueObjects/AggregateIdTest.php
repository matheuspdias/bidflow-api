<?php

use App\Shared\Domain\ValueObjects\AggregateId;

test('fromInt throws for non-positive values', function (int $value) {
    AggregateId::fromInt($value);
})->with([0, -1, -100])->throws(InvalidArgumentException::class);

test('equals compares by value', function () {
    expect(AggregateId::fromInt(1)->equals(AggregateId::fromInt(1)))->toBeTrue()
        ->and(AggregateId::fromInt(1)->equals(AggregateId::fromInt(2)))->toBeFalse();
});

test('__toString renders the underlying integer', function () {
    expect((string) AggregateId::fromInt(42))->toBe('42');
});
