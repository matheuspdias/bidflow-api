<?php

use App\Modules\Auction\Domain\Aggregates\Auction;
use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionExtended;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Domain\Exceptions\AuctionClosedException;
use App\Modules\Auction\Domain\Exceptions\BidTooLowException;
use App\Modules\Auction\Domain\Exceptions\InvalidAuctionStatusTransitionException;
use App\Modules\Auction\Domain\Exceptions\SellerCannotBidException;
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

function makeActiveAuctionEndingAt(DateTimeImmutable $endsAt): Auction
{
    $auction = Auction::schedule(
        sellerId: 1,
        categoryId: 1,
        name: 'Vintage Watch',
        description: 'A fine vintage watch.',
        startingBid: Money::of('100.00', 'USD'),
        minimumIncrement: Money::of('10.00', 'USD'),
        buyNowPrice: null,
        reservePrice: null,
        schedule: DateRange::of(new DateTimeImmutable('-1 hour'), $endsAt),
    );
    $auction->assignId(1);
    $auction->activate();
    $auction->pullDomainEvents();

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

test('placeBid rejects a bid on a SCHEDULED (not yet active) auction', function () {
    $auction = makeScheduledAuction();

    $auction->placeBid(2, Money::of('150.00', 'USD'), true);
})->throws(AuctionClosedException::class);

test('placeBid rejects a bid on a cancelled auction', function () {
    $auction = makeScheduledAuction();
    $auction->cancel();

    $auction->placeBid(2, Money::of('150.00', 'USD'), true);
})->throws(AuctionClosedException::class);

test('placeBid rejects the seller bidding on their own auction', function () {
    $auction = makeScheduledAuction(sellerId: 7);
    $auction->activate();

    $auction->placeBid(7, Money::of('150.00', 'USD'), true);
})->throws(SellerCannotBidException::class);

test('placeBid rejects a bid below current value plus minimum increment', function () {
    $auction = makeScheduledAuction();
    $auction->activate();

    // starting_bid=100, minimum_increment=10 -> minimum acceptable is 110.
    $auction->placeBid(2, Money::of('109.99', 'USD'), true);
})->throws(BidTooLowException::class);

test('placeBid accepts a bid exactly at the minimum acceptable amount', function () {
    $auction = makeScheduledAuction();
    $auction->activate();
    $auction->pullDomainEvents();

    $bid = $auction->placeBid(2, Money::of('110.00', 'USD'), true);

    expect($auction->currentValue()->equals(Money::of('110.00', 'USD')))->toBeTrue()
        ->and($auction->participantCount())->toBe(1)
        ->and($bid->bidderId())->toBe(2)
        ->and($bid->amount()->equals(Money::of('110.00', 'USD')))->toBeTrue();

    // placeBid() itself raises no event yet — the bid has no id until the
    // repository persists it. recordBidPlaced() is what the use case calls
    // once that id is known.
    expect($auction->pullDomainEvents())->toBe([]);

    $bid->assignId(99);
    $auction->recordBidPlaced($bid);

    $events = $auction->pullDomainEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(BidPlaced::class)
        ->and($events[0]->bidId)->toBe(99)
        ->and($events[0]->bidderId)->toBe(2);
});

test('recordBidPlaced refuses a bid that has not been persisted yet', function () {
    $auction = makeScheduledAuction();
    $auction->activate();

    $bid = $auction->placeBid(2, Money::of('110.00', 'USD'), true);

    $auction->recordBidPlaced($bid);
})->throws(LogicException::class);

test('placeBid does not increment participantCount for a returning bidder', function () {
    $auction = makeScheduledAuction();
    $auction->activate();

    $auction->placeBid(2, Money::of('110.00', 'USD'), true);
    $auction->placeBid(2, Money::of('130.00', 'USD'), false);

    expect($auction->participantCount())->toBe(1);
});

test('markHighestBid records the winning bid id', function () {
    $auction = makeScheduledAuction();

    expect($auction->highestBidId())->toBeNull();

    $auction->markHighestBid(42);

    expect($auction->highestBidId())->toBe(42);
});

test('extendIfWithinAntiSnipingWindow pushes ends_at forward and records AuctionExtended when inside the window', function () {
    $now = new DateTimeImmutable();
    $originalEndsAt = $now->modify('+60 seconds');
    $auction = makeActiveAuctionEndingAt($originalEndsAt);

    $extended = $auction->extendIfWithinAntiSnipingWindow($now, windowSeconds: 120, extensionSeconds: 120, maxExtensions: 5);

    expect($extended)->toBeTrue()
        ->and($auction->extensionsCount())->toBe(1)
        ->and($auction->dateRange()->end->getTimestamp())->toBe($originalEndsAt->getTimestamp() + 120);

    $events = $auction->pullDomainEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionExtended::class)
        ->and($events[0]->extensionsCount)->toBe(1);
});

test('extendIfWithinAntiSnipingWindow does nothing when the auction is not close to ending', function () {
    $now = new DateTimeImmutable();
    $auction = makeActiveAuctionEndingAt($now->modify('+1 hour'));

    $extended = $auction->extendIfWithinAntiSnipingWindow($now, windowSeconds: 120, extensionSeconds: 120, maxExtensions: 5);

    expect($extended)->toBeFalse()
        ->and($auction->extensionsCount())->toBe(0)
        ->and($auction->pullDomainEvents())->toBe([]);
});

test('extendIfWithinAntiSnipingWindow does nothing once ends_at is already in the past', function () {
    $now = new DateTimeImmutable();
    $auction = makeActiveAuctionEndingAt($now->modify('-5 seconds'));

    $extended = $auction->extendIfWithinAntiSnipingWindow($now, windowSeconds: 120, extensionSeconds: 120, maxExtensions: 5);

    expect($extended)->toBeFalse()
        ->and($auction->extensionsCount())->toBe(0);
});

test('extendIfWithinAntiSnipingWindow stops once maxExtensions is reached', function () {
    // window (500s) comfortably larger than extensionSeconds (10s): each
    // extension only pushes ends_at 10s further out, so the next check
    // (fired at roughly the same $now) still lands inside the window —
    // isolating "hit the max" from "fell outside the window" as the reason
    // extending stops.
    $now = new DateTimeImmutable();
    $auction = makeActiveAuctionEndingAt($now->modify('+10 seconds'));

    for ($i = 0; $i < 3; $i++) {
        $auction->extendIfWithinAntiSnipingWindow($now, windowSeconds: 500, extensionSeconds: 10, maxExtensions: 3);
    }

    expect($auction->extensionsCount())->toBe(3);

    $extended = $auction->extendIfWithinAntiSnipingWindow($now, windowSeconds: 500, extensionSeconds: 10, maxExtensions: 3);

    expect($extended)->toBeFalse()
        ->and($auction->extensionsCount())->toBe(3);
});
