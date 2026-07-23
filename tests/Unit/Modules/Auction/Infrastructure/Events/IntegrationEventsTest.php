<?php

use App\Modules\Auction\Domain\Events\AuctionCancelled;
use App\Modules\Auction\Domain\Events\AuctionStarted;
use App\Modules\Auction\Domain\Events\BidPlaced;
use App\Modules\Auction\Infrastructure\Events\AuctionCancelledIntegrationEvent;
use App\Modules\Auction\Infrastructure\Events\AuctionStartedIntegrationEvent;
use App\Modules\Auction\Infrastructure\Events\BidPlacedIntegrationEvent;
use App\Shared\Domain\ValueObjects\Money;

test('BidPlacedIntegrationEvent maps from the domain event with the auction.bid_placed routing key', function () {
    $occurredAt = new DateTimeImmutable('2026-01-01 12:00:00');
    $domainEvent = new BidPlaced(auctionId: 1, bidId: 2, bidderId: 3, amount: Money::of('150.00', 'USD'), occurredAt: $occurredAt);

    $integrationEvent = BidPlacedIntegrationEvent::fromDomainEvent($domainEvent);

    expect($integrationEvent->routingKey())->toBe('auction.bid_placed')
        ->and($integrationEvent->eventId())->not->toBeEmpty()
        ->and($integrationEvent->occurredAt())->toEqual($occurredAt)
        ->and($integrationEvent->toArray())->toBe([
            'event_id' => $integrationEvent->eventId(),
            'auction_id' => 1,
            'bid_id' => 2,
            'bidder_id' => 3,
            'amount' => '150.00',
            'currency' => 'USD',
            'occurred_at' => $occurredAt->format(DATE_ATOM),
        ]);
});

test('AuctionStartedIntegrationEvent maps from the domain event with the auction.auction_started routing key', function () {
    $occurredAt = new DateTimeImmutable('2026-01-01 12:00:00');
    $domainEvent = new AuctionStarted(auctionId: 5, occurredAt: $occurredAt);

    $integrationEvent = AuctionStartedIntegrationEvent::fromDomainEvent($domainEvent);

    expect($integrationEvent->routingKey())->toBe('auction.auction_started')
        ->and($integrationEvent->toArray())->toBe([
            'event_id' => $integrationEvent->eventId(),
            'auction_id' => 5,
            'occurred_at' => $occurredAt->format(DATE_ATOM),
        ]);
});

test('AuctionCancelledIntegrationEvent maps from the domain event with the auction.auction_cancelled routing key', function () {
    $occurredAt = new DateTimeImmutable('2026-01-01 12:00:00');
    $domainEvent = new AuctionCancelled(auctionId: 9, occurredAt: $occurredAt);

    $integrationEvent = AuctionCancelledIntegrationEvent::fromDomainEvent($domainEvent);

    expect($integrationEvent->routingKey())->toBe('auction.auction_cancelled')
        ->and($integrationEvent->toArray())->toBe([
            'event_id' => $integrationEvent->eventId(),
            'auction_id' => 9,
            'occurred_at' => $occurredAt->format(DATE_ATOM),
        ]);
});

test('each integration event generates a unique event id', function () {
    $domainEvent = new AuctionStarted(auctionId: 1, occurredAt: new DateTimeImmutable());

    $first = AuctionStartedIntegrationEvent::fromDomainEvent($domainEvent);
    $second = AuctionStartedIntegrationEvent::fromDomainEvent($domainEvent);

    expect($first->eventId())->not->toBe($second->eventId());
});
