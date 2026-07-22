<?php

use App\Shared\Domain\ValueObjects\Money;
use Brick\Money\Exception\MoneyMismatchException;

test('of rounds half up to the currency default scale', function () {
    expect(Money::of('10.999', 'USD')->amount())->toBe('11.00');
    expect(Money::of('10.994', 'USD')->amount())->toBe('10.99');
});

test('zero creates a zero amount in the given currency', function () {
    $money = Money::zero('USD');

    expect($money->amount())->toBe('0.00')
        ->and($money->currency())->toBe('USD');
});

test('fromMinorAmount converts minor units to the major amount', function () {
    expect(Money::fromMinorAmount(1050, 'USD')->amount())->toBe('10.50');
});

test('add sums two amounts in the same currency', function () {
    $result = Money::of('10.00', 'USD')->add(Money::of('5.50', 'USD'));

    expect($result->amount())->toBe('15.50');
});

test('subtract subtracts two amounts in the same currency', function () {
    $result = Money::of('10.00', 'USD')->subtract(Money::of('4.25', 'USD'));

    expect($result->amount())->toBe('5.75');
});

test('add throws when currencies do not match', function () {
    Money::of('10.00', 'USD')->add(Money::of('10.00', 'BRL'));
})->throws(MoneyMismatchException::class);

test('comparisons throw when currencies do not match', function () {
    Money::of('10.00', 'USD')->isGreaterThan(Money::of('10.00', 'BRL'));
})->throws(MoneyMismatchException::class);

test('isGreaterThan, isLessThan and equals compare same-currency amounts', function () {
    $ten = Money::of('10.00', 'USD');
    $five = Money::of('5.00', 'USD');
    $otherTen = Money::of('10.00', 'USD');

    expect($ten->isGreaterThan($five))->toBeTrue()
        ->and($five->isLessThan($ten))->toBeTrue()
        ->and($ten->equals($otherTen))->toBeTrue()
        ->and($ten->equals($five))->toBeFalse();
});

test('__toString renders currency and amount', function () {
    expect((string) Money::of('10.50', 'USD'))->toBe('USD 10.50');
});
