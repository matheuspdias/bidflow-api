<?php

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Exceptions\InvalidAuctionStatusTransitionException;
use App\Modules\Auction\Domain\ValueObjects\AuctionStatus;
use App\Shared\Domain\Contracts\UserIdentity;
use App\Shared\Domain\ValueObjects\DateRange;
use App\Shared\Domain\ValueObjects\Money;

function makeScheduledAuction(int $sellerId = 1): Auction
{
    $auction = Auction::schedule(
        sellerId: $sellerId,
        categoryId: 1,
        name: 'Vintage Watch',
        description: 'A fine vintage watch.',
        startingBid: Money::of('100.00', 'USD'),
        minimumIncrement: Money::of('10.00', 'USD'),
        buyNowPrice: Money::of('500.00', 'USD'),
        reservePrice: Money::of('150.00', 'USD'),
        schedule: DateRange::of(
            new DateTimeImmutable('+1 day'),
            new DateTimeImmutable('+2 days'),
        ),
    );
    $auction->assignId(1);

    return $auction;
}

test('schedule creates an auction in SCHEDULED status with current value equal to the starting bid', function () {
    $auction = makeScheduledAuction();

    expect($auction->status())->toBe(AuctionStatus::SCHEDULED)
        ->and($auction->currentValue()->equals(Money::of('100.00', 'USD')))->toBeTrue()
        ->and($auction->participantCount())->toBe(0)
        ->and($auction->viewCount())->toBe(0);
});

test('activate transitions a SCHEDULED auction to ACTIVE and records AuctionStarted', function () {
    $auction = makeScheduledAuction();

    $auction->activate();

    expect($auction->status())->toBe(AuctionStatus::ACTIVE);

    $events = $auction->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionStarted::class)
        ->and($events[0]->auctionId)->toBe(1);
});

test('activate cannot be called twice', function () {
    $auction = makeScheduledAuction();
    $auction->activate();

    $auction->activate();
})->throws(InvalidAuctionStatusTransitionException::class);

test('cancel transitions a SCHEDULED auction to CANCELLED and records AuctionCancelled', function () {
    $auction = makeScheduledAuction();

    $auction->cancel();

    expect($auction->status())->toBe(AuctionStatus::CANCELLED);

    $events = $auction->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionCancelled::class);
});

test('cancel also works from ACTIVE status', function () {
    $auction = makeScheduledAuction();
    $auction->activate();
    $auction->pullDomainEvents();

    $auction->cancel();

    expect($auction->status())->toBe(AuctionStatus::CANCELLED);
});

test('cancel cannot be called on an already cancelled auction', function () {
    $auction = makeScheduledAuction();
    $auction->cancel();

    $auction->cancel();
})->throws(InvalidAuctionStatusTransitionException::class);

test('pullDomainEvents empties the internal buffer', function () {
    $auction = makeScheduledAuction();
    $auction->activate();

    $auction->pullDomainEvents();

    expect($auction->pullDomainEvents())->toBe([]);
});

test('updateDetails is rejected once the auction is no longer SCHEDULED', function () {
    $auction = makeScheduledAuction();
    $auction->activate();

    $auction->updateDetails('New name', 'New description', 2, DateRange::of(
        new DateTimeImmutable('+1 day'),
        new DateTimeImmutable('+3 days'),
    ));
})->throws(InvalidAuctionStatusTransitionException::class);

test('isOwnedBy returns true only for the seller', function () {
    $auction = makeScheduledAuction(sellerId: 42);

    $seller = new class implements UserIdentity
    {
        public function id(): int
        {
            return 42;
        }

        public function isBlocked(): bool
        {
            return false;
        }
    };

    $stranger = new class implements UserIdentity
    {
        public function id(): int
        {
            return 99;
        }

        public function isBlocked(): bool
        {
            return false;
        }
    };

    expect($auction->isOwnedBy($seller))->toBeTrue()
        ->and($auction->isOwnedBy($stranger))->toBeFalse();
});

test('placeBid is not implemented until Fase 4', function () {
    $auction = makeScheduledAuction();

    $auction->placeBid(2, Money::of('150.00', 'USD'));
})->throws(LogicException::class);
