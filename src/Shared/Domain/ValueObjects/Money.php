<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObjects;

use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;

/**
 * Immutable money value object. Wraps brick/money so the rest of the domain
 * never depends directly on the third-party library's API.
 */
final class Money
{
    private function __construct(private readonly BrickMoney $money)
    {
    }

    public static function of(string|int|float $amount, string $currency, RoundingMode $roundingMode = RoundingMode::HalfUp): self
    {
        return new self(BrickMoney::of($amount, $currency, roundingMode: $roundingMode));
    }

    public static function zero(string $currency): self
    {
        return new self(BrickMoney::zero($currency));
    }

    public static function fromMinorAmount(int $minorAmount, string $currency): self
    {
        return new self(BrickMoney::ofMinor($minorAmount, $currency));
    }

    public function add(self $other): self
    {
        return new self($this->money->plus($other->money));
    }

    public function subtract(self $other): self
    {
        return new self($this->money->minus($other->money));
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->money->isGreaterThan($other->money);
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->money->isGreaterThanOrEqualTo($other->money);
    }

    public function isLessThan(self $other): bool
    {
        return $this->money->isLessThan($other->money);
    }

    public function equals(self $other): bool
    {
        return $this->money->isEqualTo($other->money);
    }

    public function currency(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }

    public function amount(): string
    {
        return (string) $this->money->getAmount();
    }

    public function minorAmount(): int
    {
        return $this->money->getMinorAmount()->toInt();
    }

    public function __toString(): string
    {
        return (string) $this->money;
    }
}
